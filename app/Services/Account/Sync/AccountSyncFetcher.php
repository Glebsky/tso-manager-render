<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Handles network authentication, AMF zone fetching, retry loops, error code handling (1012/1005), and friend list enrichment.
 */
class AccountSyncFetcher
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoAmfService $amfService,
        private readonly ZoneParserService $zoneParser,
    ) {}

    /**
     * Authenticate and fetch full parsed zone data.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function fetchZone(Account $account): array
    {
        $this->ensureAuthenticated($account);

        $maxRetries = 6;
        $retryDelay = 3;
        $zoneData = null;
        $errorCode = 0;
        $buildingCount = 0;
        $lastException = null;
        $hasResetSession = false;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("[AccountSync] Loading zone for account #{$account->id} (attempt {$attempt}/{$maxRetries})");
                $rawAmf = $this->amfService->getZone($account);
                $zoneData = $this->zoneParser->parse($rawAmf);

                $errorCode = (int) ($zoneData['errorCode'] ?? 0);
                $buildingCount = count($zoneData['buildings'] ?? []);

                if ($errorCode === 1012) {
                    Log::info("[AccountSync] Zone is still loading (error 1012) for account #{$account->id}; retrying in {$retryDelay}s");
                    sleep($retryDelay);

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($hasResetSession) {
                        throw new Exception(__('ui.sync.session_intercepted', ['code' => $errorCode]));
                    }
                    Log::info("[AccountSync] Session expired (error {$errorCode}) for account #{$account->id}; resetting session and logging in again");
                    @unlink($this->authService->getCookieFile($account));
                    $this->authService->login($account);
                    $this->amfService->resetClient();
                    $account->refresh();
                    $hasResetSession = true;
                    sleep(2);

                    continue;
                }

                if ($errorCode === 0 && $buildingCount > 0) {
                    $zoneData = $this->fetchFriendsListIfPossible($account, $zoneData);
                    break;
                }
            } catch (Exception $e) {
                Log::warning("[AccountSync] Attempt {$attempt}/{$maxRetries} failed for account #{$account->id}: ".$e->getMessage());
                $lastException = $e;
            }

            if ($attempt < $maxRetries) {
                sleep($retryDelay);
            }
        }

        if ($errorCode !== 0) {
            if ($errorCode === 1012) {
                throw new Exception(__('ui.sync.zone_locked'));
            }
            throw new Exception("Server error code {$errorCode}. The zone may not be loaded yet — try again in a few seconds.");
        }

        if ($buildingCount === 0) {
            throw $lastException ?: new Exception('Server returned empty zone data. Make sure the game client is closed and try again.');
        }

        return (array) $zoneData;
    }

    private function ensureAuthenticated(Account $account): void
    {
        if (! $this->authService->isAuthenticated($account)) {
            Log::info("[AccountSync] Session token for account #{$account->id} is missing or expired; logging in");
            $this->authService->login($account);
            $account->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $zoneData
     * @return array<string, mixed>
     */
    private function fetchFriendsListIfPossible(Account $account, array $zoneData): array
    {
        try {
            Log::info("[AccountSync] Loading friend list for account #{$account->id}");
            $rawFriendsAmf = $this->amfService->getFriendList($account);
            $friendsData = $this->zoneParser->parse($rawFriendsAmf);

            $parsedPlayers = $friendsData['friends'] ?? [];
            if (! empty($parsedPlayers)) {
                Log::info("[AccountSync] Friend list loaded for account #{$account->id}: ".count($parsedPlayers).' players');
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
            Log::warning("[AccountSync] Failed to load friend list for account #{$account->id}: ".$fe->getMessage());
        }

        return $zoneData;
    }
}
