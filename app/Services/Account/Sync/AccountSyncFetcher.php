<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Exceptions\AccountSyncException;
use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Handles network authentication, AMF zone fetching, retry loops, error code handling (1012/1005), and friend list enrichment.
 */
readonly class AccountSyncFetcher
{
    public function __construct(
        private TsoAuthService $authService,
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
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
        $reloginCount = 0;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("[AccountSync] Loading zone for account #{$account->id} (attempt {$attempt}/{$maxRetries})");
                $rawAmf = $this->amfService->getZone($account);
                $zoneData = $this->zoneParser->parse($rawAmf);

                $errorCode = (int) ($zoneData['errorCode'] ?? 0);
                $buildingCount = count($zoneData['buildings'] ?? []);

                if ($errorCode === 1012) {
                    if ($reloginCount < 2) {
                        Log::info("[AccountSync] Zone locked/superseded (error 1012) for account #{$account->id}; re-authenticating to take over session (relogin #".($reloginCount + 1).')');
                        $this->authService->resetSession($account);
                        $this->authService->login($account);
                        $this->amfService->invalidateSession($account->id);
                        $account->refresh();
                        $reloginCount++;
                        sleep(2);

                        continue;
                    }

                    sleep($retryDelay);

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($reloginCount < 2) {
                        Log::info("[AccountSync] Session expired (error {$errorCode}) for account #{$account->id}; re-authenticating (relogin #".($reloginCount + 1).')');
                        $this->authService->resetSession($account);
                        $this->authService->login($account);
                        $this->amfService->invalidateSession($account->id);
                        $account->refresh();
                        $reloginCount++;
                        sleep(2);

                        continue;
                    }

                    sleep($retryDelay);

                    continue;
                }

                if ($errorCode === 0 && $buildingCount > 0) {
                    $zoneData = $this->fetchFriendsListIfPossible($account, $zoneData);
                    break;
                }
            } catch (Exception $e) {
                Log::warning("[AccountSync] Attempt {$attempt}/{$maxRetries} failed for account #{$account->id}: ".$e->getMessage());
                $lastException = $e;

                if ($this->authService->isCaptchaOr2faError($e->getMessage())) {
                    throw $e;
                }
            }

            if ($attempt < $maxRetries) {
                sleep($retryDelay);
            }
        }

        if ($errorCode !== 0) {
            if ($errorCode === 1012) {
                throw new AccountSyncException(__('ui.sync.zone_locked'), 409);
            }
            throw new AccountSyncException("Server error code {$errorCode}. The zone may not be loaded yet — try again in a few seconds.", 502);
        }

        if ($buildingCount === 0) {
            throw $lastException ?: new AccountSyncException('Server returned empty zone data. Make sure the game client is closed and try again.', 502);
        }

        return (array) $zoneData;
    }

    /**
     * @throws Exception
     */
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
                    if ($puid !== null && $ownerUid !== null && (int) $puid === (int) $ownerUid) {
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
