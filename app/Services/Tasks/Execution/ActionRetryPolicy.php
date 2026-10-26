<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\ZoneCacheInvalidator;
use App\Services\TsoAmfService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

final readonly class ActionRetryPolicy
{
    public function __construct(
        private GameSessionRecovery $sessionRecovery,
        private TsoAmfService $amfService,
        private ZoneCacheInvalidator $zoneCache,
    ) {}

    /**
     * Execute an action callback with session error retry policy.
     *
     * This is the ONLY layer that re-logs in (FR-009), and it does so at most
     * once per action: a second session failure after a fresh login means the
     * account really cannot talk to the game right now, and every extra login
     * creates yet another competing game session (error 1012).
     *
     * @param  callable(): string  $action
     *
     * @throws Exception
     */
    public function execute(Account $account, string $taskTypeStr, callable $action): string
    {
        $maxAttempts = max(1, (int) config('game.tasks.max_action_attempts', 3));
        $sessionCodes = array_map('intval', (array) config('game.tasks.relogin_errors', [1005]));
        $transportCodes = array_map('intval', (array) config('game.tasks.transport_retry_errors', [1012]));
        $transportDelay = (int) config('game.tasks.transport_retry_delay', 3);
        $busyDelay = max(1, (int) config('game.tasks.session_busy_retry_delay', 5));
        $accountId = $account->id;
        $username = $account->username ?? $account->nickname ?? "account#{$accountId}";
        $reloggedIn = false;
        $identityRecovered = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): attempt {$attempt}/{$maxAttempts} starting");
            $startTime = microtime(true);

            $attemptIdentity = $this->sessionRecovery->currentIdentity($account);
            try {
                $result = $action();
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): SUCCEEDED on attempt {$attempt}/{$maxAttempts} ({$durationMs}ms)");

                return $result;
            } catch (SessionBusyException $e) {
                // Another process holds the account's game session (FR-008).
                // This is not a session problem: wait and retry, never re-login.
                if ($attempt >= $maxAttempts) {
                    Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): FAILED after {$attempt}/{$maxAttempts} attempts, session still busy ({$e->getMessage()})");

                    throw $e;
                }

                Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): session busy, retrying in {$busyDelay}s without re-login (attempt {$attempt}/{$maxAttempts})");
                Sleep::for($busyDelay)->seconds();

                continue;
            } catch (SessionExpiredException $e) {
                // The load server rejected our stored web session (HTTP-level).
                if ($attempt >= $maxAttempts || $reloggedIn) {
                    Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): FAILED on attempt {$attempt}/{$maxAttempts}, session rejected".($reloggedIn ? ' even after a fresh login' : '')." ({$e->getMessage()})");

                    throw $e;
                }

                Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): session rejected by load server; performing the single forced re-login and retrying (attempt {$attempt}/{$maxAttempts})");
                $this->relogin($account, $identityRecovered);
                $reloggedIn = true;

                continue;
            } catch (GameServerErrorException $e) {
                $code = (int) $e->getCode();
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($attempt >= $maxAttempts) {
                    Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): FAILED after {$attempt}/{$maxAttempts} attempts, last game error {$code} ({$e->getMessage()}) [{$durationMs}ms]");

                    throw $e;
                }

                // 1012 (NEWER_SESSION_DETECTED / Zone loading):
                if ($attempt === 1 && in_array($code, $transportCodes, true)) {
                    Log::info("[TaskExecution] Action [{$taskTypeStr}] hit game error {$code} (Zone loading/unready) for account #{$accountId} ({$username}); warming up zone and retrying in {$transportDelay}s (attempt {$attempt}/{$maxAttempts})");

                    $this->amfService->resetClient($accountId);

                    try {
                        $this->amfService->ensureZoneLoaded($account, maxAttempts: 2);
                    } catch (Throwable $zoneEx) {
                        Log::warning("[TaskExecution] Zone warm-up check for account #{$accountId} returned: ".$zoneEx->getMessage());
                    }

                    Sleep::for($transportDelay)->seconds();

                    continue;
                }

                if ($code === 1012) {
                    if ($identityRecovered) {
                        throw $e;
                    }
                    $identityRecovered = true;
                    $this->sessionRecovery->renewGameIdentity($account, $attemptIdentity);
                    try {
                        $this->sessionRecovery->enterHomeZone($account);
                    } catch (SessionExpiredException|GameServerErrorException $zoneError) {
                        if (($zoneError instanceof GameServerErrorException && (int) $zoneError->getCode() !== 1005) || $reloggedIn) {
                            throw $zoneError;
                        }
                        $reloggedIn = true;
                        $this->relogin($account, $identityRecovered);
                    }

                    continue;
                }

                if (in_array($code, $sessionCodes, true)) {
                    if ($reloggedIn) {
                        // FR-009: at most one re-login per action. The fresh session
                        // is rejected too — another login would only add one more
                        // competing game session.
                        Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): game error {$code} persists after the single re-login; giving up on attempt {$attempt}/{$maxAttempts}");

                        throw $e;
                    }

                    Log::info("[TaskExecution] Action [{$taskTypeStr}] hit session conflict/expired (error {$code}) for account #{$accountId} ({$username}); re-authenticating and retrying (attempt {$attempt}/{$maxAttempts})");

                    $this->relogin($account, $identityRecovered);
                    $reloggedIn = true;
                    Sleep::for(2)->seconds();

                    continue;
                }

                // 550 (Invalid value provided / visitor session expired):
                if ($attempt === 1 && $code === 550 && $taskTypeStr === 'apply_buff') {
                    Log::info("[TaskExecution] Action [{$taskTypeStr}] hit game error 550 (visitor session expired / invalid value) for account #{$accountId} ({$username}); invalidating friend zone cache and retrying in 1s (attempt {$attempt}/{$maxAttempts})");

                    $this->zoneCache->invalidateFriendZones($accountId);
                    Sleep::for(1)->seconds();

                    continue;
                }

                Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): aborted on attempt {$attempt}/{$maxAttempts} with non-retryable game error {$code} ({$e->getMessage()})");

                throw $e;
            }
        }

        throw new \RuntimeException("Action [{$taskTypeStr}] failed.");
    }

    /**
     * The one and only forced re-login of an action: drop the web session and
     * the shared game session (client id is kept — FR-009), log in again and
     * confirm home-zone entry before the next action attempt.
     *
     * @throws Exception
     */
    private function relogin(Account $account, bool &$identityRecovered): void
    {
        $this->sessionRecovery->reauthenticate($account);
        $zoneIdentity = $this->sessionRecovery->currentIdentity($account);
        try {
            $this->sessionRecovery->enterHomeZone($account);
        } catch (GameServerErrorException $error) {
            if ((int) $error->getCode() !== 1012 || $identityRecovered) {
                throw $error;
            }
            $identityRecovered = true;
            $this->sessionRecovery->renewGameIdentity($account, $zoneIdentity);
            $this->sessionRecovery->enterHomeZone($account);
        }
    }
}
