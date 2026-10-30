<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Exceptions\AccountSyncException;
use App\Exceptions\AuthChallengeException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Handles network authentication, AMF zone fetching, retry loops, error code handling (1012/1005), and friend list enrichment.
 */
readonly class AccountSyncFetcher
{
    /** At most one forced re-login per sync (FR-009): each login creates a new game session. */
    private const int MAX_RELOGINS = 1;

    public function __construct(
        private TsoAuthService $authService,
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
        private GameSessionRecovery $sessionRecovery,
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
        $reloginCount = $this->ensureAuthenticatedWithRecovery($account) ? 1 : 0;

        $identityRecovered = false;
        $maxRetries = 6;
        $retryDelay = 3;
        $zoneData = null;
        $errorCode = 0;
        $buildingCount = 0;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("[AccountSync] Loading zone for account #{$account->id} (attempt {$attempt}/{$maxRetries})");
                $attemptIdentity = $this->sessionRecovery->currentIdentity($account);
                $rawAmf = $this->amfService->getZone($account);
                $zoneData = $this->zoneParser->parse($rawAmf);

                $rawCode = $zoneData['errorCode'] ?? null;
                if (! is_int($rawCode) && ! (is_string($rawCode) && ctype_digit($rawCode))) {
                    throw new \RuntimeException('Cannot confirm home zone login: invalid response');
                }
                $errorCode = (int) $rawCode;
                $buildingCount = count($zoneData['buildings'] ?? []);

                if ($errorCode === 1012) {
                    // Same policy as GameSessionGuard: take the superseded game
                    // session over like a fresh client (the next getZone() is the
                    // confirming CMD_ZONE_LOGIN), then one forced login at most.
                    if (! $identityRecovered && $attempt < $maxRetries) {
                        $identityRecovered = true;
                        Log::info("[AccountSync] Game session superseded (error 1012) for account #{$account->id}; taking it over like a fresh client (attempt {$attempt}/{$maxRetries}).");
                        try {
                            $this->sessionRecovery->renewGameIdentity($account, $attemptIdentity);
                        } catch (SessionBusyException $busy) {
                            // Recovery never ran: stop rather than retry the rejected identity.
                            throw new AccountSyncException(__('ui.sync.zone_locked'), 409, [], $busy);
                        }

                        continue;
                    }

                    if ($reloginCount < self::MAX_RELOGINS && $attempt < $maxRetries) {
                        $reloginCount++;
                        $this->relogin($account, 'error 1012 persists on the fresh game session');

                        continue;
                    }

                    Log::warning("[AccountSync] Zone still locked (error 1012) after game identity recovery for account #{$account->id}. Retrying in {$retryDelay}s (attempt {$attempt}/{$maxRetries}).");
                    if ($attempt < $maxRetries) {
                        Sleep::for($retryDelay)->seconds();
                    }

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($reloginCount < self::MAX_RELOGINS && $attempt < $maxRetries) {
                        $reloginCount++;
                        $this->relogin($account, "session expired (error {$errorCode})");
                        Sleep::for(2)->seconds();

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

                if ($e instanceof AccountSyncException) {
                    throw $e;
                }

                if ($e instanceof AuthChallengeException || $this->authService->isCaptchaOr2faError($e->getMessage())) {
                    throw $e;
                }

                if ($e instanceof SessionExpiredException
                    && $reloginCount < self::MAX_RELOGINS && $attempt < $maxRetries) {
                    $reloginCount++;
                    $this->relogin($account, "session error during fetch: {$e->getMessage()}");
                }
            }

            if ($attempt < $maxRetries) {
                Sleep::for($retryDelay)->seconds();
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
        $this->sessionRecovery->reset($account);
    }

    /**
     * The single forced re-login of a sync. Failure aborts recovery;
     * CAPTCHA/2FA propagates unchanged. The next zone read confirms 1002.
     *
     * @throws Exception
     */
    private function relogin(Account $account, string $why): void
    {
        Log::info("[AccountSync] {$why} for account #{$account->id}; resetting session and re-authenticating");

        try {
            $this->sessionRecovery->reauthenticate($account);
            // The next bounded getZone() is the same CMD_ZONE_LOGIN(1002)
            // used by task recovery; sync also consumes its parsed zone data.
        } catch (Exception $reloginEx) {
            Log::warning("[AccountSync] Re-login failed for account #{$account->id}; not continuing zone requests");

            if ($reloginEx instanceof AuthChallengeException || $this->authService->isCaptchaOr2faError($reloginEx->getMessage())) {
                throw $reloginEx;
            }

            throw new AccountSyncException('Session recovery failed.', 502, [], $reloginEx);
        }
    }

    /**
     * Authenticate normally; only a typed session rejection permits the one clean forced login.
     *
     * @throws Exception
     */
    private function ensureAuthenticatedWithRecovery(Account $account): bool
    {
        try {
            $this->ensureAuthenticated($account);

            return false;
        } catch (Exception $e) {
            if ($e instanceof AuthChallengeException || $this->authService->isCaptchaOr2faError($e->getMessage())) {
                throw $e;
            }

            if (! $e instanceof SessionExpiredException) {
                throw $e;
            }

            Log::warning("[AccountSync] Initial authentication failed for account #{$account->id}: {$e->getMessage()}. Resetting session state and retrying...");
            $this->sessionRecovery->reauthenticate($account);

            return true;
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
