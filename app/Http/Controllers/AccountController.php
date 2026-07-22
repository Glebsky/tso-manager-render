<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use App\Services\AccountSyncService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    public function sync(Account $account, AccountSyncService $syncService)
    {
        try {
            $zoneData = $syncService->sync($account);

            return response()->json([
                'success' => true,
                'message' => 'Zone synced successfully.',
                'account' => $account->fresh(),
                'zone_data' => $zoneData,
            ]);
        } catch (Exception $e) {
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
                    $parsed = $this->zoneParser->parse($result);
                    $errorCode = $parsed['errorCode'] ?? 0;
                    if ($errorCode !== 0) {
                        $errorMsg = \App\Services\GameErrorResolver::getMessage((int) $errorCode);
                        throw new \App\Exceptions\GameServerErrorException((int) $errorCode, $errorMsg);
                    }
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

    /**
     * Get target friend's zone data.
     */
    public function friendZone(Account $account, $friendId)
    {
        $friendId = (int) $friendId;
        if ($friendId <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('ui.account.friend_zone.invalid_id'),
            ], 400);
        }

        $zoneData = $account->zone_data ? json_decode($account->zone_data, true) : [];
        $friends = $zoneData['friends'] ?? [];
        $friend = null;
        foreach ($friends as $f) {
            if (isset($f['id']) && (int) $f['id'] === $friendId) {
                $friend = $f;
                break;
            }
        }

        if (! $friend) {
            return response()->json([
                'success' => false,
                'message' => __('ui.account.friend_zone.not_in_friends_list'),
            ], 403);
        }

        $cacheKey = "friend-zone:{$account->id}:{$friendId}";
        $staleCacheKey = "friend-zone-stale:{$account->id}:{$friendId}";
        $cachedZone = Cache::get($cacheKey);

        if ($cachedZone) {
            $friendZoneData = json_decode($cachedZone, true);
        } else {
            try {
                if (! $this->authService->isAuthenticated($account)) {
                    $this->authService->login($account);
                    $account->refresh();
                }

                Log::info("Fetching friend zone AMF for account {$account->id}, friend {$friendId}");
                $rawAmf = $this->amfService->getZone($account, $friendId);
                $friendZoneData = $this->zoneParser->parse($rawAmf);

                $errorCode = $friendZoneData['errorCode'] ?? 0;
                if ($errorCode !== 0) {
                    $staleCache = Cache::get($staleCacheKey);
                    if ($staleCache) {
                        Log::warning("Game server error {$errorCode} when fetching friend zone {$friendId} for account {$account->id}, falling back to stale cache");
                        $friendZoneData = json_decode($staleCache, true);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => __('ui.account.friend_zone.server_error', [
                                'code' => $errorCode,
                                'error' => \App\Services\GameErrorResolver::getMessage((int) $errorCode),
                            ]),
                        ], 500);
                    }
                } else {
                    $jsonEncoded = json_encode($friendZoneData);
                    Cache::put($cacheKey, $jsonEncoded, 3600);
                    Cache::put($staleCacheKey, $jsonEncoded, 86400);
                }
            } catch (Exception $e) {
                Log::error("Failed to fetch zone of friend {$friendId} for account {$account->id}: ".$e->getMessage());

                $staleCache = Cache::get($staleCacheKey);
                if ($staleCache) {
                    Log::warning("Exception when fetching friend zone {$friendId} for account {$account->id}, falling back to stale cache");
                    $friendZoneData = json_decode($staleCache, true);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => __('ui.account.friend_zone.load_failed', ['message' => $e->getMessage()]),
                    ], 500);
                }
            }
        }

        $buildings = [];
        foreach ($friendZoneData['buildings'] ?? [] as $b) {
            $buildings[] = [
                'buildingName_string' => $b['buildingName_string'] ?? $b['buildingName'] ?? 'Building',
                'buildingName' => $b['buildingName'] ?? $b['buildingName_string'] ?? 'Building',
                'buildingGrid' => $b['buildingGrid'] ?? $b['grid'] ?? 0,
                'upgradeLevel' => $b['upgradeLevel'] ?? $b['level'] ?? 1,
                'isProductionActive' => $b['isProductionActive'] ?? false,
                'buildingMode' => $b['buildingMode'] ?? 0,
            ];
        }

        return response()->json([
            'success' => true,
            'friend' => [
                'id' => $friendId,
                'username' => $friend['username'] ?? $friend['nickname'] ?? 'Unknown',
            ],
            'buildings' => $buildings,
        ]);
    }
}
