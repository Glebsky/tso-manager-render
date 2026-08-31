<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\UnknownTaskActionException;
use App\Models\Account;
use App\Services\Tasks\TaskHandlerRegistry;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;

final readonly class SingleActionExecutor
{
    public function __construct(
        private TaskHandlerRegistry $handlerRegistry,
        private GameResponseValidator $responseValidator,
        private ActionRetryPolicy $retryPolicy,
    ) {}

    /**
     * Execute a single action with retry policy and payload validation.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws Exception
     */
    public function executeWithRetry(Account $account, TaskType|string $taskType, array $payload): string
    {
        $taskTypeStr = $taskType instanceof TaskType ? $taskType->value : $taskType;

        return $this->retryPolicy->execute($account, $taskTypeStr, function () use ($account, $taskTypeStr, $payload) {
            return $this->executeSingleAction($account, $taskTypeStr, $payload);
        });
    }

    /**
     * Execute a single action step via TaskHandlerRegistry.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws GameServerErrorException
     * @throws UnknownTaskActionException
     * @throws BindingResolutionException
     */
    public function executeSingleAction(Account $account, TaskType|string $taskType, array $payload): string
    {
        $taskTypeStr = $taskType instanceof TaskType ? $taskType->value : $taskType;

        $result = $this->handlerRegistry->getHandler($taskTypeStr)->handle($account, $payload);

        $this->responseValidator->validateAndParse($result);

        return $result;
    }
}
