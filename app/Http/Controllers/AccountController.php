<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    private TsoAuthService $authService;

    private TsoAmfService $amfService;

    private ZoneParserService $zoneParser;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService, ZoneParserService $zoneParser)
    {
        $this->authService = $authService;
        $this->amfService = $amfService;
        $this->zoneParser = $zoneParser;
    }

    /**
     * List all accounts.
     */
    public function index()
    {
        $accounts = Account::latest()->get();

        return response()->json($accounts);
    }

    /**
     * Create a new account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'region' => 'required|string|max:10',
        ]);

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account added.',
            'account' => $account,
        ], 201);
    }

    /**
     * Show account details.
     */
    public function show(Account $account)
    {
        return response()->json($account);
    }

    /**
     * Delete an account.
     */
    public function destroy(Account $account)
    {
        // Clean up cookie file
        $cookieFile = $this->authService->getCookieFile($account);
        if (file_exists($cookieFile)) {
            @unlink($cookieFile);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.',
        ]);
    }

    /**
     * Sync: login if needed, fetch zone data, parse and cache.
     * Only saves zone_data if buildings were found (prevents overwriting valid data).
     */
    public function sync(Account $account)
    {
        try {
            Log::info("Syncing account {$account->id} ({$account->username})");
            $account->update(['status' => 'syncing']);

            // Login if tokens are missing
            if (! $this->authService->isAuthenticated($account)) {
                Log::info("Account {$account->id} token missing or expired, performing login");
                $this->authService->login($account);
                $account->refresh();
            }

            // Fetch and parse zone with auto-retry loop (handles game server warm-up)
            $maxRetries = 6;
            $retryDelay = 3; // seconds
            $zoneData = null;
            $rawAmf = null;
            $errorCode = 0;
            $buildingCount = 0;
            $lastException = null;

            $hasResetSession = false;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info("Fetching zone AMF for account {$account->id} (attempt {$attempt}/{$maxRetries})");
                    $rawAmf = $this->amfService->getZone($account);
                    $zoneData = $this->zoneParser->parse($rawAmf);

                    $errorCode = $zoneData['errorCode'] ?? 0;
                    $buildingCount = count($zoneData['buildings'] ?? []);

                    if ($errorCode === 1012) {
                        Log::info("Received error 1012 (Zone loading) for account {$account->id}. Waiting {$retryDelay}s and retrying...");
                        sleep($retryDelay);

                        continue;
                    }

                    if ($errorCode === 1005) {
                        if ($hasResetSession) {
                            throw new Exception("Сессия перехвачена другой игрой (ошибка {$errorCode}). Синхронизация отменена во избежание блокировки.");
                        }
                        Log::info("Received error {$errorCode} (Session expired) for account {$account->id}. Resetting session...");
                        @unlink($this->authService->getCookieFile($account));
                        $this->authService->login($account);
                        $this->amfService->resetClient();
                        $account->refresh();
                        $hasResetSession = true;
                        sleep(2);

                        continue;
                    }

                    if ($errorCode === 0 && $buildingCount > 0) {
                        file_put_contents(storage_path('app/debug_zone.amf'), $rawAmf);

                        // Also fetch the friends list!
                        try {
                            Log::info("Fetching friend list AMF for account {$account->id}");
                            $rawFriendsAmf = $this->amfService->getFriendList($account);
                            $friendsData = $this->zoneParser->parse($rawFriendsAmf);

                            $parsedPlayers = $friendsData['players'] ?? [];
                            if (! empty($parsedPlayers)) {
                                Log::info('Successfully fetched '.count($parsedPlayers)." players from friends list for account {$account->id}");
                                $friendsList = [];
                                $ownerUid = $zoneData['userID'] ?? null;

                                foreach ($parsedPlayers as $p) {
                                    $puid = $p['userID'] ?? null;
                                    if ($puid && $ownerUid && $puid == $ownerUid) {
                                        continue;
                                    }
                                    $friendsList[] = [
                                        'username' => $p['username_string'] ?? $p['username'] ?? $p['nickname'] ?? 'Unknown',
                                        'nickname' => $p['nickname'] ?? $p['username_string'] ?? $p['username'] ?? 'Unknown',
                                        'playerLevel' => $p['playerLevel'] ?? $p['level'] ?? 1,
                                        'level' => $p['playerLevel'] ?? $p['level'] ?? 1,
                                        'avatarId' => $p['avatarId'] ?? 1,
                                        'onlineStatus' => $p['onlineStatus'] ?? false,
                                    ];
                                }
                                $zoneData['friends'] = $friendsList;
                            }
                        } catch (\Exception $fe) {
                            Log::warning("Failed to fetch friends list for account {$account->id}: ".$fe->getMessage());
                        }

                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning("Attempt {$attempt}/{$maxRetries} failed for account {$account->id}: ".$e->getMessage());
                    $lastException = $e;
                }

                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                }
            }

            if ($errorCode !== 0) {
                $account->update(['status' => 'error']);
                if ($errorCode === 1012) {
                    throw new Exception('Игровая зона занята или заблокирована (ошибка 1012). Пожалуйста, выйдите из игры через кнопку «Выход» в меню игры (а не просто закрыв окно), подождите пару минут и попробуйте синхронизацию снова.');
                }
                throw new Exception("Server error code {$errorCode}. The zone may not be loaded yet — try again in a few seconds.");
            }

            if ($buildingCount === 0) {
                $account->update(['status' => 'error']);
                throw $lastException ?: new Exception('Server returned empty zone data. Make sure the game client is closed and try again.');
            }

            // Save
            $account->update([
                'zone_data' => json_encode($zoneData, JSON_UNESCAPED_UNICODE),
                'last_sync_at' => now(),
                'status' => 'online',
            ]);

            $specialistCount = count($zoneData['specialists'] ?? []);
            $buffCount = count($zoneData['buffs'] ?? []);
            $resourceCount = count($zoneData['resources'] ?? []);

            Log::info("Account {$account->id} synced successfully. Level: ".($zoneData['level'] ?? 'N/A').', Server: '.($zoneData['gameWorldName'] ?? 'N/A').", Buildings: {$buildingCount}, Resources: {$resourceCount}");

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'success',
                'message' => 'Zone synced: '.$buildingCount.' buildings, '.$resourceCount.' resources, '.$specialistCount.' specialists, '.$buffCount.' buffs.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Zone synced successfully.',
                'account' => $account,
                'zone_data' => $zoneData,
            ]);
        } catch (Exception $e) {
            Log::error("Sync failed for account {$account->id}: ".$e->getMessage(), ['exception' => $e]);

            // Don't overwrite status if it was already set to 'error' above
            if ($account->status !== 'error') {
                $account->update(['status' => 'error']);
            }

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
                'account' => $account->fresh(),
            ], 500);
        }
    }

    /**
     * Execute an action on an account (stop_production, start_production, apply_buff, send_specialist).
     */
    public function action(Request $request, Account $account)
    {
        $request->validate([
            'action_type' => 'required|string|in:stop_production,start_production,apply_buff,send_specialist',
        ]);

        try {
            // Ensure authenticated
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $actionType = $request->input('action_type');
            $result = '';

            switch ($actionType) {
                case 'stop_production':
                    $request->validate(['grid' => 'required|integer']);
                    $result = $this->amfService->stopProduction($account, (int) $request->input('grid'));
                    break;

                case 'start_production':
                    $request->validate(['grid' => 'required|integer']);
                    $result = $this->amfService->startProduction($account, (int) $request->input('grid'));
                    break;

                case 'apply_buff':
                    $request->validate([
                        'grid' => 'required|integer',
                        'unique_id1' => 'required|integer',
                        'unique_id2' => 'required|integer',
                    ]);
                    $result = $this->amfService->applyBuff(
                        $account,
                        (int) $request->input('grid'),
                        (int) $request->input('unique_id1'),
                        (int) $request->input('unique_id2')
                    );
                    break;

                case 'send_specialist':
                    $request->validate([
                        'task_type' => 'required|integer',
                        'sub_task_id' => 'required|integer',
                        'unique_id1' => 'required|integer',
                        'unique_id2' => 'required|integer',
                    ]);
                    $result = $this->amfService->sendSpecialist(
                        $account,
                        (int) $request->input('task_type'),
                        (int) $request->input('sub_task_id'),
                        (int) $request->input('unique_id1'),
                        (int) $request->input('unique_id2')
                    );
                    break;
            }

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'success',
                'message' => "Action [{$actionType}] executed successfully.",
            ]);

            return response()->json([
                'success' => true,
                'message' => "Action [{$actionType}] executed successfully.",
            ]);
        } catch (Exception $e) {
            BotLog::create([
                'account_id' => $account->id,
                'level' => 'error',
                'message' => "Action [{$request->input('action_type')}] failed: ".$e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Action failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually update the account's session tokens.
     */
    public function updateSession(Request $request, Account $account)
    {
        $validated = $request->validate([
            'dso_auth_token' => 'required|string',
            'dso_auth_user' => 'required|string',
            'bb_url' => 'required|url',
        ]);

        $account->update([
            'dso_auth_token' => $validated['dso_auth_token'],
            'dso_auth_user' => $validated['dso_auth_user'],
            'bb_url' => $validated['bb_url'],
            'status' => 'online',
        ]);

        // Clear cached client connection so the new tokens are used immediately
        $this->amfService->resetClient();

        BotLog::create([
            'account_id' => $account->id,
            'level' => 'success',
            'message' => 'Сессия обновлена вручную.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Сессия успешно обновлена вручную.',
            'account' => $account,
        ]);
    }
}
