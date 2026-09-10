<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ActionRetryPolicy
{
    public function __construct(
        private TsoAuthService $authService,
        private TsoAmfService $amfService,
    ) {}

    /**
     * Execute an action callback with session error retry policy.
     *
     * @param  callable(): string  $action
     *
     * @throws Exception
     */
    public function execute(Account $account, string $taskTypeStr, callable $action): string
    {
        $maxAttempts = (int) config('game.tasks.max_action_attempts', 3);
        $sessionCodes = (array) config('game.tasks.relogin_errors', [1005]);
        $transportCodes = (array) config('game.tasks.transport_retry_errors', [1012]);
        $transportDelay = (int) config('game.tasks.transport_retry_delay', 3);
        $accountId = $account->id;
        $username = $account->username ?? $account->nickname ?? "account#{$accountId}";

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): attempt {$attempt}/{$maxAttempts} starting");
            $startTime = microtime(true);

            try {
                $result = $action();
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                Log::info("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): SUCCEEDED on attempt {$attempt}/{$maxAttempts} ({$durationMs}ms)");

                return $result;
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

                    sleep($transportDelay);

                    continue;
                }

                if (in_array($code, $sessionCodes, true) || in_array($code, $transportCodes, true)) {
                    Log::info("[TaskExecution] Action [{$taskTypeStr}] hit session conflict/expired (error {$code}) for account #{$accountId} ({$username}); re-authenticating and retrying (attempt {$attempt}/{$maxAttempts})");

                    $this->authService->resetSession($account);
                    $this->amfService->invalidateSession($accountId);
                    $this->amfService->resetClient($accountId);
                    $this->authService->ensureAuthenticated($account);
                    $account->refresh();
                    sleep(2);

                    continue;
                }

                Log::error("[TaskExecution] Action [{$taskTypeStr}] for account #{$accountId} ({$username}): aborted on attempt {$attempt}/{$maxAttempts} with non-retryable game error {$code} ({$e->getMessage()})");

                throw $e;
            }
        }

        throw new \RuntimeException("Action [{$taskTypeStr}] failed.");
    }
}
