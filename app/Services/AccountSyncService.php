<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\BotLog;
use Exception;
use Illuminate\Support\Facades\Log;

class AccountSyncService
{
    private TsoAuthService $authService;

    private TsoAmfService $amfService;

    private ZoneParserService $zoneParser;

    public function __construct(
        TsoAuthService $authService,
        TsoAmfService $amfService,
        ZoneParserService $zoneParser
    ) {
        $this->authService = $authService;
        $this->amfService = $amfService;
        $this->zoneParser = $zoneParser;
    }

    /**
     * Sync: login if needed, fetch zone data, parse and cache.
     *
     * @throws Exception
     */
    public function sync(Account $account): array
    {
        try {
            Log::info("Syncing account {$account->id} ({$account->username})");
            $account->update(['status' => 'syncing']);

            $this->ensureAuthenticated($account);

            $zoneData = $this->fetchAndParseZone($account);

            $account->update([
                'zone_data' => json_encode($zoneData, JSON_UNESCAPED_UNICODE),
                'last_sync_at' => now(),
                'status' => 'online',
            ]);

            $this->logSuccess($account, $zoneData);

            return $zoneData;
        } catch (Exception $e) {
            Log::error("Sync failed for account {$account->id}: ".$e->getMessage(), ['exception' => $e]);

            if ($account->status !== 'error') {
                $account->update(['status' => 'error']);
            }

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function ensureAuthenticated(Account $account): void
    {
        if (! $this->authService->isAuthenticated($account)) {
            Log::info("Account {$account->id} token missing or expired, performing login");
            $this->authService->login($account);
            $account->refresh();
        }
    }

    private function fetchAndParseZone(Account $account): array
    {
        $maxRetries = 6;
        $retryDelay = 3; // seconds
        $zoneData = null;
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
                        throw new Exception(__('ui.sync.session_intercepted', ['code' => $errorCode]));
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
                    $zoneData = $this->fetchFriendsListIfPossible($account, $zoneData);
                    break;
                }
            } catch (Exception $e) {
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
                throw new Exception(__('ui.sync.zone_locked'));
            }
            throw new Exception("Server error code {$errorCode}. The zone may not be loaded yet — try again in a few seconds.");
        }

        if ($buildingCount === 0) {
            $account->update(['status' => 'error']);
            throw $lastException ?: new Exception('Server returned empty zone data. Make sure the game client is closed and try again.');
        }

        return $zoneData;
    }

    private function fetchFriendsListIfPossible(Account $account, array $zoneData): array
    {
        try {
            Log::info("Fetching friend list AMF for account {$account->id}");
            $rawFriendsAmf = $this->amfService->getFriendList($account);
            file_put_contents(storage_path('app/debug_friends_list.amf'), $rawFriendsAmf);
            $friendsData = $this->zoneParser->parse($rawFriendsAmf);

            $parsedPlayers = $friendsData['friends'] ?? [];
            if (! empty($parsedPlayers)) {
                Log::info('Successfully fetched '.count($parsedPlayers)." players from friends list for account {$account->id}");
                $friendsList = [];
                $ownerUid = $zoneData['userID'] ?? null;

                foreach ($parsedPlayers as $p) {
                    $puid = $p['id'] ?? $p['userID'] ?? null;
                    if ($puid && $ownerUid && $puid == $ownerUid) {
                        continue;
                    }
                    $friendsList[] = [
                        'id' => $puid,
                        'username' => $p['username_string'] ?? $p['username'] ?? $p['nickname'] ?? 'Unknown',
                        'nickname' => $p['nickname'] ?? $p['username_string'] ?? $p['username'] ?? 'Unknown',
                        'playerLevel' => $p['playerLevel'] ?? $p['level'] ?? 1,
                        'level' => $p['playerLevel'] ?? $p['level'] ?? 1,
                        'avatarId' => $p['avatarId'] ?? 1,
                        'friendSince' => $p['friendSince'] ?? null,
                    ];
                }
                $zoneData['friends'] = $friendsList;
            }
        } catch (Exception $fe) {
            Log::warning("Failed to fetch friends list for account {$account->id}: ".$fe->getMessage());
        }

        return $zoneData;
    }

    private function logSuccess(Account $account, array $zoneData): void
    {
        $buildingCount = count($zoneData['buildings'] ?? []);
        $specialistCount = count($zoneData['specialists'] ?? []);
        $buffCount = count($zoneData['buffs'] ?? []);
        $resourceCount = count($zoneData['resources'] ?? []);

        Log::info("Account {$account->id} synced successfully. Level: ".($zoneData['level'] ?? 'N/A').', Server: '.($zoneData['gameWorldName'] ?? 'N/A').", Buildings: {$buildingCount}, Resources: {$resourceCount}");

        BotLog::create([
            'account_id' => $account->id,
            'level' => 'success',
            'message' => 'Zone synced: '.$buildingCount.' buildings, '.$resourceCount.' resources, '.$specialistCount.' specialists, '.$buffCount.' buffs.',
        ]);
    }
}
