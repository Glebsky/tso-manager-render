<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskType;
use App\Models\Account;
use App\Services\Tasks\TaskHandlerRegistry;

final class SingleActionExecutor
{
    public function __construct(
        private readonly TaskHandlerRegistry $handlerRegistry,
        private readonly GameResponseValidator $responseValidator,
        private readonly ActionRetryPolicy $retryPolicy,
    ) {}

    /**
     * Execute a single action with retry policy and payload validation.
     *
     * @param  array<string, mixed>  $payload
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
     */
    public function executeSingleAction(Account $account, TaskType|string $taskType, array $payload): string
    {
        $taskTypeStr = $taskType instanceof TaskType ? $taskType->value : $taskType;

        $handler = $this->handlerRegistry->getHandler($taskTypeStr);
        $result = $handler->handle($account, $payload);

        $this->responseValidator->validateAndParse($result);

        return $result;
    }
}
