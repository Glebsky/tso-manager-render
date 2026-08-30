<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskType;
use App\Exceptions\TaskAccountNotFoundException;
use App\Exceptions\TaskExecutionException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\SequenceStepExecutor;
use App\Services\Tasks\Execution\SingleActionExecutor;
use App\Services\Tasks\Execution\TaskExecutionGuard;
use App\Services\Tasks\Execution\TaskResultSummary;
use App\Services\Tasks\Execution\TaskStateWriter;
use Exception;
use Throwable;

readonly class TaskExecutionService
{
    public function __construct(
        private TsoAuthService $authService,
        private TaskExecutionGuard $guard,
        private TaskStateWriter $stateWriter,
        private SingleActionExecutor $singleActionExecutor,
        private SequenceStepExecutor $sequenceStepExecutor,
    ) {}

    /**
     * Execute the given scheduled task.
     *
     * @throws TaskExecutionException
     * @throws Throwable
     * @throws TaskAccountNotFoundException
     * @throws TaskInactiveException
     * @throws TokenMismatchException
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
                ? (string) json_encode($e->toPayload(), JSON_THROW_ON_ERROR)
                : $e->getMessage();

            $payload = $task->payload ?? [];
            $payload['step_results'] = [['status' => 'failed', 'error' => $errorMsg]];
            $this->stateWriter->markFailed($task, $account->id, $errorMsg, $payload);

            throw $e;
        }
    }

    /**
     * Execute a single non-sequence task.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws Throwable
     */
    private function executeSingleTask(ScheduledTask $task, Account $account, array $payload): string
    {
        $taskTypeStr = $task->task_type->value;
        $result = $this->singleActionExecutor->executeWithRetry($account, $taskTypeStr, $payload);

        $payload['step_results'] = [['status' => 'completed', 'error' => null]];
        $resultSummary = TaskResultSummary::formatSingle($result);

        $this->stateWriter->markCompleted($task, $account->id, $resultSummary, $payload);

        return $result;
    }

    /**
     * Execute exactly one pending step of a sequence task.
     *
     * @return array{finished: bool, nextDelay: int}
     *
     * @throws Exception
     * @throws Throwable
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
     *
     * @throws Throwable
     */
    public function executeSingleActionWithRetry(Account $account, TaskType|string $taskType, array $payload): string
    {
        return $this->singleActionExecutor->executeWithRetry($account, $taskType, $payload);
    }

    /**
     * Execute a single action step via TaskHandlerRegistry.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws Throwable
     */
    public function executeSingleAction(Account $account, TaskType|string $taskType, array $payload): string
    {
        return $this->singleActionExecutor->executeSingleAction($account, $taskType, $payload);
    }
}
