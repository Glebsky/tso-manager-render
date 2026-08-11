<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskType;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\SequenceStepExecutor;
use App\Services\Tasks\Execution\SingleActionExecutor;
use App\Services\Tasks\Execution\TaskExecutionGuard;
use App\Services\Tasks\Execution\TaskResultSummary;
use App\Services\Tasks\Execution\TaskStateWriter;
use Exception;
use Throwable;

class TaskExecutionService
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TaskExecutionGuard $guard,
        private readonly TaskStateWriter $stateWriter,
        private readonly SingleActionExecutor $singleActionExecutor,
        private readonly SequenceStepExecutor $sequenceStepExecutor,
    ) {}

    /**
     * Execute the given scheduled task.
     *
     * @throws Exception
     */
    public function execute(ScheduledTask $task, ?string $expectedToken = null, bool $force = false): string
    {
        $account = $this->guard->ensureCanExecute($task, $expectedToken, $force);
        $this->stateWriter->markRunning($task);

        try {
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $payload = $task->payload ?? [];

            if ($task->task_type === TaskType::Sequence) {
                return $this->sequenceStepExecutor->executeInlineSequence($task, $account, $payload);
            }

            return $this->executeSingleTask($task, $account, $payload);
        } catch (Throwable $e) {
            $errorMsg = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload())
                : $e->getMessage();

            $payload = $task->payload ?? [];
            $payload['step_results'] = [['status' => 'failed', 'error' => $errorMsg]];
            $this->stateWriter->markFailed($task, (int) $account->id, $errorMsg, $payload);

            throw $e;
        }
    }

    /**
     * Execute a single non-sequence task.
     *
     * @param  array<string, mixed>  $payload
     */
    private function executeSingleTask(ScheduledTask $task, Account $account, array $payload): string
    {
        $taskTypeStr = $task->task_type instanceof TaskType ? $task->task_type->value : (string) $task->task_type;
        $result = $this->singleActionExecutor->executeWithRetry($account, $taskTypeStr, $payload);

        $payload['step_results'] = [['status' => 'completed', 'error' => null]];
        $resultSummary = TaskResultSummary::formatSingle($result, true);

        $this->stateWriter->markCompleted($task, (int) $account->id, $resultSummary, $payload);

        return $result;
    }

    /**
     * Execute exactly one pending step of a sequence task.
     *
     * @return array{finished: bool, nextDelay: int}
     *
     * @throws Exception
     */
    public function executeSequenceStep(ScheduledTask $task, ?string $expectedToken = null, bool $force = false): array
    {
        $account = $this->guard->ensureCanExecute($task, $expectedToken, $force);

        return $this->sequenceStepExecutor->executeSingleStep($task, $account);
    }

    /**
     * Execute a single action step with session error (1012, 1005) retry logic.
     *
     * @param  array<string, mixed>  $payload
     */
    public function executeSingleActionWithRetry(Account $account, TaskType|string $taskType, array $payload, int $maxAttempts = 2): string
    {
        return $this->singleActionExecutor->executeWithRetry($account, $taskType, $payload);
    }

    /**
     * Execute a single action step via TaskHandlerRegistry.
     *
     * @param  array<string, mixed>  $payload
     */
    public function executeSingleAction(Account $account, TaskType|string $taskType, array $payload): string
    {
        return $this->singleActionExecutor->executeSingleAction($account, $taskType, $payload);
    }
}
