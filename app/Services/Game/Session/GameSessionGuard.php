<?php

declare(strict_types=1);

namespace App\Services\Game\Session;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The single place that decides how a game call recovers its session.
 *
 * Mirrors the original game client:
 *  - the stored web token is always tried first (the caller authenticates normally);
 *  - 1012 "Newer game session detected": our game session was superseded, so we
 *    take it over like a freshly started client — new client identity, new
 *    load-server session/DSId and a confirmed CMD_ZONE_LOGIN(1002), web token kept;
 *  - the token itself rejected (HTTP session expired / 1005), or a fresh game
 *    session that still cannot enter the zone: one forced login per call;
 *  - another process holds the account mutex: wait and retry, never re-login.
 *
 * Guards nest: a guarded call made inside another guarded call for the same
 * account (e.g. a zone read inside a task action) runs as-is and lets errors
 * bubble up, so the outer guard owns the single recovery budget.
 */
final class GameSessionGuard
{
    /** @var array<int, true> */
    private static array $active = [];

    public function __construct(private readonly GameSessionRecovery $recovery) {}

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $call
     * @param  (callable(GameServerErrorException, int): bool)|null  $retryGameError  caller-specific retry for other game errors
     * @return TResult
     *
     * @throws Exception
     */
    public function run(Account $account, string $label, callable $call, ?callable $retryGameError = null): mixed
    {
        $accountId = (int) $account->id;
        if (isset(self::$active[$accountId])) {
            return $call();
        }

        self::$active[$accountId] = true;
        try {
            return $this->attempt($account, $label, $call, $retryGameError);
        } finally {
            unset(self::$active[$accountId]);
        }
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $call
     * @param  (callable(GameServerErrorException, int): bool)|null  $retryGameError
     * @return TResult
     *
     * @throws Exception
     */
    private function attempt(Account $account, string $label, callable $call, ?callable $retryGameError): mixed
    {
        $maxAttempts = max(1, (int) config('game.tasks.max_action_attempts', 3));
        $sessionCodes = array_map('intval', (array) config('game.tasks.relogin_errors', [1005]));
        $takeoverCodes = array_map('intval', (array) config('game.tasks.transport_retry_errors', [1012]));
        $busyDelay = max(1, (int) config('game.tasks.session_busy_retry_delay', 5));
        $accountId = $account->id;
        $who = "{$label} for account #{$accountId} (".($account->username ?? $account->nickname ?? "account#{$accountId}").')';
        $reloggedIn = false;
        $tookOver = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $startTime = microtime(true);
            $attemptIdentity = $this->recovery->currentIdentity($account);
            $last = $attempt >= $maxAttempts;

            try {
                $result = $call();
                if ($attempt > 1) {
                    $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                    Log::info("[GameSession] {$who}: succeeded on attempt {$attempt}/{$maxAttempts} ({$durationMs}ms)");
                }

                return $result;
            } catch (SessionBusyException $e) {
                if ($last) {
                    Log::error("[GameSession] {$who}: failed after {$attempt}/{$maxAttempts} attempts, session still busy ({$e->getMessage()})");

                    throw $e;
                }
                Log::info("[GameSession] {$who}: session busy, retrying in {$busyDelay}s without re-login (attempt {$attempt}/{$maxAttempts})");
                Sleep::for($busyDelay)->seconds();

                continue;
            } catch (SessionExpiredException $e) {
                if ($last || $reloggedIn) {
                    Log::error("[GameSession] {$who}: session rejected on attempt {$attempt}/{$maxAttempts}".($reloggedIn ? ' even after a fresh login' : '')." ({$e->getMessage()})");

                    throw $e;
                }
                Log::info("[GameSession] {$who}: stored session rejected by load server; performing the single forced login (attempt {$attempt}/{$maxAttempts})");
                $reloggedIn = true;
                $this->relogin($account, $tookOver);

                continue;
            } catch (GameServerErrorException $e) {
                $code = (int) $e->getCode();
                if ($last) {
                    Log::error("[GameSession] {$who}: failed after {$attempt}/{$maxAttempts} attempts, last game error {$code} ({$e->getMessage()})");

                    throw $e;
                }

                if (in_array($code, $takeoverCodes, true)) {
                    if (! $tookOver) {
                        Log::info("[GameSession] {$who}: game error {$code} (session superseded); taking the game session over like a fresh client (attempt {$attempt}/{$maxAttempts})");
                        $tookOver = true;
                        $this->takeOver($account, $attemptIdentity, $reloggedIn);

                        continue;
                    }
                    if (! $reloggedIn) {
                        Log::info("[GameSession] {$who}: game error {$code} persists on the fresh game session; performing the single forced login (attempt {$attempt}/{$maxAttempts})");
                        $reloggedIn = true;
                        $this->relogin($account, $tookOver);

                        continue;
                    }
                    Log::error("[GameSession] {$who}: game error {$code} persists after session takeover and a fresh login; another client keeps taking the session over");

                    throw $e;
                }

                if (in_array($code, $sessionCodes, true)) {
                    if ($reloggedIn) {
                        Log::error("[GameSession] {$who}: game error {$code} persists after the single forced login; giving up on attempt {$attempt}/{$maxAttempts}");

                        throw $e;
                    }
                    Log::info("[GameSession] {$who}: game session expired (error {$code}); performing the single forced login (attempt {$attempt}/{$maxAttempts})");
                    $reloggedIn = true;
                    $this->relogin($account, $tookOver);
                    Sleep::for(2)->seconds();

                    continue;
                }

                if ($retryGameError !== null && $retryGameError($e, $attempt)) {
                    continue;
                }

                Log::warning("[GameSession] {$who}: aborted on attempt {$attempt}/{$maxAttempts} with non-retryable game error {$code} ({$e->getMessage()})");

                throw $e;
            }
        }

        throw new \RuntimeException("{$label} failed.");
    }

    /**
     * Fresh-client takeover on the stored token. When the fresh game session
     * cannot confirm zone entry (still 1012, or the token turns out expired)
     * the single forced login is used instead.
     *
     * @throws Exception
     */
    private function takeOver(Account $account, int $rejectedIdentity, bool &$reloggedIn): void
    {
        $this->recovery->renewGameIdentity($account, $rejectedIdentity);

        try {
            $this->recovery->enterHomeZone($account);
        } catch (SessionExpiredException|GameServerErrorException $zoneError) {
            $code = $zoneError instanceof GameServerErrorException ? (int) $zoneError->getCode() : null;
            $recoverable = $code === null || in_array($code, [1005, 1012], true);
            if ($reloggedIn || ! $recoverable) {
                throw $zoneError;
            }

            Log::info("[GameSession] Account #{$account->id}: fresh game session could not enter the zone ({$zoneError->getMessage()}); performing the single forced login");
            $reloggedIn = true;
            $tookOver = true;
            $this->relogin($account, $tookOver);
        }
    }

    /**
     * The one forced login of a call: drop web + shared game session, log in
     * again and confirm home-zone entry before the call is retried.
     *
     * @throws Exception
     */
    private function relogin(Account $account, bool &$tookOver): void
    {
        $this->recovery->reauthenticate($account);
        $zoneIdentity = $this->recovery->currentIdentity($account);

        try {
            $this->recovery->enterHomeZone($account);
        } catch (GameServerErrorException $error) {
            if ((int) $error->getCode() !== 1012 || $tookOver) {
                throw $error;
            }
            $tookOver = true;
            $this->recovery->renewGameIdentity($account, $zoneIdentity);
            $this->recovery->enterHomeZone($account);
        }
    }
}
