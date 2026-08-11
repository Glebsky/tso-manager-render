<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Exception;
use Illuminate\Support\Facades\Log;

final class ActionRetryPolicy
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoAmfService $amfService,
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
        $maxAttempts = (int) config('game.tasks.max_action_attempts', 2);
        $retryableCodes = (array) config('game.tasks.retry_session_errors', [1005, 1012]);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $action();
            } catch (GameServerErrorException $e) {
                if (in_array($e->getCode(), $retryableCodes, true) && $attempt < $maxAttempts) {
                    Log::info("[TaskExecution] Action [{$taskTypeStr}] hit game error {$e->getCode()}; resetting session and retrying (attempt {$attempt}/{$maxAttempts})");
                    $this->authService->resetSession($account);
                    $this->authService->login($account);
                    $this->amfService->resetClient();
                    $account->refresh();
                    sleep(2);

                    continue;
                }
                throw $e;
            }
        }

        throw new Exception("Action [{$taskTypeStr}] failed.");
    }
}
