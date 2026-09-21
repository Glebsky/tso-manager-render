<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LogLevel;
use App\Enums\TaskResultPrefix;
use App\Enums\TaskType;
use App\Exceptions\Contracts\SkippableStepExceptionInterface;
use App\Exceptions\InvalidTaskTypeException;
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
use App\Services\Tasks\TaskActivityLogger;
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
        private TaskActivityLogger $activityLogger,
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
            $this->authService->ensureAuthenticated($account);

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
        $payload['task_id'] = $task->id;
        $payload['execution_token'] = $task->execution_token;

        try {
            $result = $this->singleActionExecutor->executeWithRetry($account, $taskTypeStr, $payload);

            $payload['step_results'] = [['status' => 'completed', 'error' => null]];
            $resultSummary = TaskResultSummary::formatSingle($result);

            $this->stateWriter->markCompleted($task, $account->id, $resultSummary, $payload);

            return $result;
        } catch (SkippableStepExceptionInterface $e) {
            $payload['step_results'] = [['status' => 'skipped', 'error' => null, 'reason' => $e->getMessage()]];
            $resultSummary = TaskResultPrefix::Skipped->format($e->getMessage());

            $this->stateWriter->markCompleted($task, $account->id, $resultSummary, $payload);

            return $resultSummary;
        }
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

    /**
     * Execute a single action inside a sequence task manually (step play button).
     *
     * @return array{status: string, error: ?string, reason?: string}
     *
     * @throws TaskExecutionException
     * @throws InvalidTaskTypeException
     * @throws TaskAccountNotFoundException
     * @throws Throwable
     */
    public function executeSingleSequenceAction(ScheduledTask $task, int $actionIndex): array
    {
        if ($task->task_type !== TaskType::Sequence) {
            throw new InvalidTaskTypeException("Task #{$task->id} is not a sequence task.", 422, ['id' => $task->id, 'type' => $task->task_type->value]);
        }

        if ($task->status->isBusy()) {
            throw new TaskExecutionException('error.task_busy', ['id' => $task->id], 422);
        }

        $payload = $task->payload ?? [];
        $actions = $payload['actions'] ?? [];
        if (! isset($actions[$actionIndex])) {
            throw new TaskExecutionException('error.action_index_invalid', ['index' => $actionIndex], 422);
        }

        $account = $task->account;
        if (! $account) {
            $task->load('account');
            $account = $task->account;
        }
        if (! $account) {
            throw new TaskAccountNotFoundException($task->id);
        }

        $this->authService->ensureAuthenticated($account);

        $action = $actions[$actionIndex];
        $actionType = (string) $action['task_type'];
        $actionPayload = (array) ($action['payload'] ?? []);
        $actionPayload['task_id'] = $task->id;

        $stepResults = $payload['step_results'] ?? [];

        try {
            $this->singleActionExecutor->executeWithRetry($account, $actionType, $actionPayload);
            $stepResults[$actionIndex] = [
                'status' => 'completed',
                'error' => null,
            ];

            $this->activityLogger->logTaskEvent(
                $account->id,
                $task->id,
                LogLevel::Success,
                __('logs.task.single_action_completed', ['name' => $task->name, 'step' => $actionIndex + 1, 'type' => $actionType])
            );
        } catch (SkippableStepExceptionInterface $e) {
            $stepResults[$actionIndex] = [
                'status' => 'skipped',
                'error' => null,
                'reason' => $e->getMessage(),
            ];

            $this->activityLogger->logTaskEvent(
                $account->id,
                $task->id,
                LogLevel::Warning,
                __('logs.task.single_action_skipped', ['name' => $task->name, 'step' => $actionIndex + 1, 'type' => $actionType, 'reason' => $e->getMessage()])
            );
        } catch (Throwable $e) {
            $readableError = $e->getMessage();
            $errorPayload = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload(), JSON_THROW_ON_ERROR)
                : $readableError;

            $stepResults[$actionIndex] = [
                'status' => 'failed',
                'error' => $errorPayload,
            ];

            $this->activityLogger->logTaskEvent(
                $account->id,
                $task->id,
                LogLevel::Error,
                __('logs.task.single_action_failed', ['name' => $task->name, 'step' => $actionIndex + 1, 'type' => $actionType, 'error' => $readableError])
            );
        }

        $payload['step_results'] = $stepResults;
        $task->update(['payload' => $payload]);

        /** @var array{status: string, error: ?string, reason?: string} */
        return $stepResults[$actionIndex];
    }
}
