<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Exceptions\AccountSyncException;
use App\Exceptions\SessionExpiredException;
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
        $this->ensureAuthenticatedWithRecovery($account);

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
                        try {
                            $this->resetSessionState($account);
                            $this->authService->ensureAuthenticated($account);
                            $account->refresh();
                        } catch (Exception $reloginEx) {
                            Log::warning("[AccountSync] Background re-login for account #{$account->id} encountered: {$reloginEx->getMessage()}; proceeding to retry zone fetch");
                            if ($this->authService->isCaptchaOr2faError($reloginEx->getMessage())) {
                                throw $reloginEx;
                            }
                        }
                        $reloginCount++;
                        sleep(3);

                        continue;
                    }

                    sleep($retryDelay);

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($reloginCount < 2) {
                        Log::info("[AccountSync] Session expired (error {$errorCode}) for account #{$account->id}; re-authenticating (relogin #".($reloginCount + 1).')');
                        try {
                            $this->resetSessionState($account);
                            $this->authService->ensureAuthenticated($account);
                            $account->refresh();
                        } catch (Exception $reloginEx) {
                            Log::warning("[AccountSync] Background re-login for account #{$account->id} encountered: {$reloginEx->getMessage()}; proceeding to retry zone fetch");
                            if ($this->authService->isCaptchaOr2faError($reloginEx->getMessage())) {
                                throw $reloginEx;
                            }
                        }
                        $reloginCount++;
                        sleep(2);

                        continue;
                    }

                    throw new AccountSyncException(__('ui.game_error.1005'), 401);
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

                if ($e instanceof SessionExpiredException || stripos($e->getMessage(), 'session') !== false) {
                    if ($reloginCount < 2) {
                        Log::info("[AccountSync] Caught session error during fetch for account #{$account->id}: {$e->getMessage()}; resetting session and re-authenticating (relogin #".($reloginCount + 1).')');
                        try {
                            $this->resetSessionState($account);
                            $this->authService->ensureAuthenticated($account);
                            $account->refresh();
                        } catch (Exception $reloginEx) {
                            Log::warning("[AccountSync] Recovery re-login for account #{$account->id} encountered: {$reloginEx->getMessage()}");
                            if ($this->authService->isCaptchaOr2faError($reloginEx->getMessage())) {
                                throw $reloginEx;
                            }
                        }
                        $reloginCount++;
                    }
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
            if ($errorCode === 1005) {
                throw new AccountSyncException(__('ui.game_error.1005'), 401);
            }
            throw new AccountSyncException("Server error code {$errorCode}. The zone may not be loaded yet — try again in a few seconds.", 502);
        }

        if ($buildingCount === 0) {
            throw $lastException ?: new AccountSyncException('Server returned empty zone data. Make sure the game client is closed and try again.', 502);
        }

        return (array) $zoneData;
    }

    /**
     * Completely resets active session state on both Auth and AMF layers.
     */
    public function resetSessionState(Account $account): void
    {
        $this->authService->resetSession($account);
        $this->amfService->invalidateSession($account->id);
        $this->amfService->resetClient($account->id);
    }

    /**
     * Authenticate the account, with an automatic clean session reset and retry if initial attempt fails.
     *
     * @throws Exception
     */
    private function ensureAuthenticatedWithRecovery(Account $account): void
    {
        try {
            $this->ensureAuthenticated($account);
        } catch (Exception $e) {
            if ($this->authService->isCaptchaOr2faError($e->getMessage())) {
                throw $e;
            }

            Log::warning("[AccountSync] Initial authentication failed for account #{$account->id}: {$e->getMessage()}. Resetting session state and retrying...");
            $this->resetSessionState($account);

            $this->ensureAuthenticated($account);
        }
    }

    /**
     * @throws Exception
     */
    private function ensureAuthenticated(Account $account): void
    {
        $this->authService->ensureAuthenticated($account);
    }

    /**
     * @param  array<string, mixed>  $zoneData
     * @return array<string, mixed>
     */
    private function fetchFriendsListIfPossible(Account $account, array $zoneData): array
    {
        $existingFriends = is_array($account->zone_data) && is_array($account->zone_data['friends'] ?? null)
            ? $account->zone_data['friends']
            : [];
        $zoneData['friends'] = $existingFriends;

        try {
            Log::info("[AccountSync] Loading friend list for account #{$account->id}");
            $rawFriendsAmf = $this->amfService->getFriendList($account);
            $friendsData = $this->zoneParser->parse($rawFriendsAmf);

            $errorCode = (int) ($friendsData['errorCode'] ?? 0);
            if ($errorCode !== 0) {
                Log::warning("[AccountSync] Failed to load friend list for account #{$account->id}: Server returned error code {$errorCode}");

                return $zoneData;
            }

            if (! isset($friendsData['friends']) || ! is_array($friendsData['friends'])) {
                Log::warning("[AccountSync] Friend list response missing 'friends' array for account #{$account->id}");

                return $zoneData;
            }

            $parsedPlayers = $friendsData['friends'];
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

            Log::info("[AccountSync] Friend list loaded for account #{$account->id}: ".count($friendsList).' players');
            $zoneData['friends'] = $friendsList;
        } catch (Exception $fe) {
            Log::warning("[AccountSync] Failed to load friend list for account #{$account->id}: ".$fe->getMessage());
        }

        return $zoneData;
    }
}
