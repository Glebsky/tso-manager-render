<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\LogLevel;
use App\Enums\TaskType;
use App\Exceptions\InvalidTaskTypeException;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskActivityLogger;
use App\Services\TsoAuthService;
use Throwable;

final class SequenceStepExecutor
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly SingleActionExecutor $actionExecutor,
        private readonly TaskStateWriter $stateWriter,
        private readonly TaskActivityLogger $activityLogger,
    ) {}

    /**
     * Execute a sequence task inline (all steps in one pass).
     *
     * @param  array<string, mixed>  $payload
     */
    public function executeInlineSequence(ScheduledTask $task, Account $account, array $payload): string
    {
        $actions = $payload['actions'] ?? [];
        $resultsSummary = [];
        $completedSteps = (int) ($task->completed_steps ?? 0);
        $stepResults = $payload['step_results'] ?? [];
        $hasStepError = false;
        $hasStepSuccess = false;

        foreach ($actions as $index => $action) {
            $actionType = (string) $action['task_type'];
            $actionPayload = (array) ($action['payload'] ?? []);
            $delay = (int) ($action['delay_seconds'] ?? 0);

            if ($index < $completedSteps) {
                $resultsSummary[] = __('tasks.step.skipped', ['step' => $index + 1, 'type' => $actionType]);
                if (! isset($stepResults[$index])) {
                    $stepResults[$index] = [
                        'status' => 'completed',
                        'error' => null,
                    ];
                }

                continue;
            }

            try {
                $stepResult = $this->actionExecutor->executeWithRetry($account, $actionType, $actionPayload);

                $stepResults[$index] = [
                    'status' => 'completed',
                    'error' => null,
                ];
                $hasStepSuccess = true;

                $resultsSummary[] = __('tasks.step.ok', ['step' => $index + 1, 'type' => $actionType, 'bytes' => strlen($stepResult)]);

                $this->activityLogger->logTaskEvent(
                    (int) $account->id,
                    (int) $task->id,
                    LogLevel::Success,
                    __('logs.task.step_completed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType])
                );
            } catch (Throwable $e) {
                $hasStepError = true;
                $errorMsg = $e instanceof TaskExecutionException
                    ? (string) json_encode($e->toPayload())
                    : $e->getMessage();

                $stepResults[$index] = [
                    'status' => 'failed',
                    'error' => $errorMsg,
                ];

                $resultsSummary[] = __('tasks.step.error', ['step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg]);

                $this->activityLogger->logTaskEvent(
                    (int) $account->id,
                    (int) $task->id,
                    LogLevel::Error,
                    __('logs.task.step_failed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg])
                );
            }

            $completedSteps = $index + 1;
            $payload['step_results'] = $stepResults;
            $this->stateWriter->updateStepProgress($task, $completedSteps, $payload);

            if ($index < count($actions) - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        $result = implode('; ', $resultsSummary);
        $resultSummary = TaskResultSummary::formatSequence($result, $hasStepError, $hasStepSuccess);

        $this->stateWriter->markSequenceFinished($task, (int) $account->id, $resultSummary, $hasStepError, $payload);

        return $result;
    }

    /**
     * Execute exactly one step of a sequence task (queued step execution).
     *
     * @return array{finished: bool, nextDelay: int}
     */
    public function executeSingleStep(ScheduledTask $task, Account $account): array
    {
        if ($task->task_type !== TaskType::Sequence) {
            throw new InvalidTaskTypeException("Task #{$task->id} is not a sequence task.", 422, ['id' => $task->id, 'type' => $task->task_type->value]);
        }

        $payload = $task->payload ?? [];
        $actions = $payload['actions'] ?? [];
        $stepResults = $payload['step_results'] ?? [];
        $index = (int) ($task->completed_steps ?? 0);

        if ($index === 0 && ! empty($stepResults)) {
            $stepResults = [];
            unset($payload['step_results']);
        }

        if (empty($actions) || $index >= count($actions)) {
            return $this->finalizeSequenceStep($task, (int) $account->id, $payload, $stepResults);
        }

        $this->stateWriter->markRunning($task, $payload);

        if (! $this->authService->isAuthenticated($account)) {
            $this->authService->login($account);
            $account->refresh();
        }

        $action = $actions[$index];
        $actionType = (string) $action['task_type'];
        $actionPayload = (array) ($action['payload'] ?? []);
        $delay = (int) ($action['delay_seconds'] ?? 0);

        try {
            $stepResult = $this->actionExecutor->executeWithRetry($account, $actionType, $actionPayload);

            $stepResults[$index] = [
                'status' => 'completed',
                'error' => null,
            ];

            $this->activityLogger->logTaskEvent(
                (int) $account->id,
                (int) $task->id,
                LogLevel::Success,
                __('logs.task.step_completed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType])
            );
        } catch (Throwable $e) {
            $errorMsg = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload())
                : $e->getMessage();

            $stepResults[$index] = [
                'status' => 'failed',
                'error' => $errorMsg,
            ];

            $this->activityLogger->logTaskEvent(
                (int) $account->id,
                (int) $task->id,
                LogLevel::Error,
                __('logs.task.step_failed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg])
            );
        }

        $payload['step_results'] = $stepResults;
        $this->stateWriter->updateStepProgress($task, $index + 1, $payload);

        if ($index + 1 >= count($actions)) {
            return $this->finalizeSequenceStep($task, (int) $account->id, $payload, $stepResults);
        }

        return [
            'finished' => false,
            'nextDelay' => $delay,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int|string, mixed>  $stepResults
     * @return array{finished: bool, nextDelay: int}
     */
    private function finalizeSequenceStep(ScheduledTask $task, int $accountId, array $payload, array $stepResults): array
    {
        $hasStepError = false;
        $hasStepSuccess = false;
        $summaryParts = [];

        foreach ($stepResults as $i => $stepResult) {
            $stepNumber = (int) $i + 1;
            $stepStatus = is_array($stepResult) ? ($stepResult['status'] ?? null) : null;
            $stepError = is_array($stepResult) ? ($stepResult['error'] ?? 'unknown') : 'unknown';

            if ($stepStatus === 'failed') {
                $hasStepError = true;
                $summaryParts[] = (string) __('tasks.step.error_short', ['step' => $stepNumber, 'error' => $stepError]);
            } else {
                $hasStepSuccess = true;
                $summaryParts[] = (string) __('tasks.step.ok_short', ['step' => $stepNumber]);
            }
        }

        $result = implode('; ', $summaryParts);
        $resultSummary = TaskResultSummary::formatSequence($result, $hasStepError, $hasStepSuccess);

        $this->stateWriter->markSequenceFinished($task, $accountId, $resultSummary, $hasStepError, $payload);

        return [
            'finished' => true,
            'nextDelay' => 0,
        ];
    }
}
