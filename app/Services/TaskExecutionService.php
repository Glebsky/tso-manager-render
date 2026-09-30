<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskResultPrefix;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\Contracts\SkippableStepExceptionInterface;
use App\Exceptions\InvalidTaskTypeException;
use App\Exceptions\TaskAccountNotFoundException;
use App\Exceptions\TaskCancelledException;
use App\Exceptions\TaskExecutionException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Game\Zone\ZoneCacheInvalidator;
use App\Services\Tasks\Execution\SequenceRunner;
use App\Services\Tasks\Execution\SequenceRunResult;
use App\Services\Tasks\Execution\SingleActionExecutor;
use App\Services\Tasks\Execution\StepRunner;
use App\Services\Tasks\Execution\TaskExecutionGuard;
use App\Services\Tasks\Execution\TaskResultSummary;
use App\Services\Tasks\Execution\TaskStateMachine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

readonly class TaskExecutionService
{
    public function __construct(
        private TsoAuthService $authService,
        private TaskExecutionGuard $guard,
        private TaskStateMachine $stateMachine,
        private SingleActionExecutor $singleActionExecutor,
        private SequenceRunner $sequenceRunner,
        private StepRunner $stepRunner,
        private ZoneCacheInvalidator $zoneCache,
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
        $token = $expectedToken ?? $task->execution_token;
        if ($token === null) {
            $token = (string) Str::uuid();
            $task->update([
                'execution_token' => $token,
                'status' => TaskStatus::Queued,
            ]);
        }

        if (! $this->stateMachine->start($task, $token)) {
            Log::warning(sprintf(
                '[TaskExecutionService] Task #%d start CAS failed (token=%s, status=%s). Aborting.',
                $task->id,
                substr($token, 0, 8),
                $task->status->value
            ));

            return '';
        }

        try {
            if ($task->task_type === TaskType::Sequence) {
                // No budget: the inline run goes to the end in this process.
                return $this->sequenceRunner->run($task, $account, $token)->summary;
            }

            $this->authService->ensureAuthenticated($account);

            return $this->executeSingleTask($task, $account, $task->payload ?? []);
        } catch (Throwable $e) {
            $errorMsg = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload(), JSON_THROW_ON_ERROR)
                : $e->getMessage();

            $payload = $task->payload ?? [];
            $payload['step_results'] = [['status' => 'failed', 'error' => $errorMsg]];
            $this->stateMachine->finish($task, $token, TaskStatus::Failed, TaskResultSummary::formatSingle($errorMsg, false), $payload, $account->id);

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
        $token = (string) $task->execution_token;
        $taskTypeStr = $task->task_type->value;
        $payload['task_id'] = $task->id;
        $payload['execution_token'] = $task->execution_token;

        try {
            $result = $this->singleActionExecutor->executeWithRetry($account, $taskTypeStr, $payload);

            $payload['step_results'] = [['status' => 'completed', 'error' => null]];
            $resultSummary = TaskResultSummary::formatSingle($result);

            $this->stateMachine->finish($task, $token, TaskStatus::Completed, $resultSummary, $payload, $account->id);

            return $result;
        } catch (SkippableStepExceptionInterface $e) {
            $payload['step_results'] = [['status' => 'skipped', 'error' => null, 'reason' => $e->getMessage()]];
            $resultSummary = TaskResultPrefix::Skipped->format($e->getMessage());

            $this->stateMachine->finish($task, $token, TaskStatus::Completed, $resultSummary, $payload, $account->id);

            return $resultSummary;
        }
    }

    /**
     * Run the pending steps of a reserved sequence task within a time budget
     * (queue path). The caller re-dispatches on hand-off.
     *
     * @throws InvalidTaskTypeException
     * @throws TaskCancelledException
     * @throws TaskInactiveException
     * @throws TaskAccountNotFoundException
     * @throws TokenMismatchException
     * @throws Throwable
     */
    public function runSequence(ScheduledTask $task, string $token, bool $force, int $budgetSeconds): SequenceRunResult
    {
        if ($task->task_type !== TaskType::Sequence) {
            throw new InvalidTaskTypeException("Task #{$task->id} is not a sequence task.", 422, ['id' => $task->id, 'type' => $task->task_type->value]);
        }

        $account = $this->guard->ensureCanExecute($task, $token, $force);

        return $this->sequenceRunner->run($task, $account, $token, $budgetSeconds);
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

        // When manually executing a single buff action, invalidate cached zone
        // so it always queries the live state from the game server.
        if ($actionType === 'apply_buff') {
            $scope = (string) (($action['payload'] ?? [])['target_scope'] ?? 'self');
            if ($scope === 'friend') {
                $this->zoneCache->invalidateFriendZones($account->id);
            } else {
                $this->zoneCache->invalidateHomeZone($account->id);
            }
        }

        $outcome = $this->stepRunner->run($task, $account, $actionIndex, (array) $action, manual: true);

        $stepResults = $payload['step_results'] ?? [];
        $stepResults[$actionIndex] = $outcome->toArray();
        $payload['step_results'] = $stepResults;
        $task->update(['payload' => $payload]);

        /** @var array{status: string, error: ?string, reason?: string} */
        return $stepResults[$actionIndex];
    }
}
