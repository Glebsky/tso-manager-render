<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\LogLevel;
use App\Exceptions\BuffUnavailableException;
use App\Exceptions\Contracts\SkippableStepExceptionInterface;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskActivityLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes one sequence step and turns whatever happened into a StepOutcome.
 *
 * This is the single place that maps step exceptions to persisted results
 * and activity-log lines; both the sequence engine and the manual "play one
 * step" button go through it. Step failures never escape: a failed step is
 * a result, not an exception of the run.
 */
final readonly class StepRunner
{
    public function __construct(
        private SingleActionExecutor $actionExecutor,
        private TaskActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $action  `payload.actions[$index]`
     * @param  bool  $manual  operator ran this single step by hand (different log wording, no run token)
     */
    public function run(ScheduledTask $task, Account $account, int $index, array $action, bool $manual = false): StepOutcome
    {
        $actionType = (string) $action['task_type'];
        $actionPayload = (array) ($action['payload'] ?? []);
        $actionPayload['task_id'] = $task->id;

        if (! $manual) {
            $actionPayload['execution_token'] = $task->execution_token;
        }

        $logParams = ['name' => $task->name, 'step' => $index + 1, 'type' => $actionType];

        try {
            $message = $this->actionExecutor->executeWithRetry($account, $actionType, $actionPayload);

            $this->log($account, $task, LogLevel::Success, $manual ? 'logs.task.single_action_completed' : 'logs.task.step_completed', $logParams);

            return StepOutcome::completed($index, $actionType, $message);
        } catch (SkippableStepExceptionInterface $e) {
            $reason = $e->getMessage();
            $buff = null;

            if ($e instanceof BuffUnavailableException) {
                $buff = (string) ($actionPayload['buff_name'] ?? $actionPayload['buff_raw_name'] ?? 'buff');
            }

            if ($manual) {
                $this->log($account, $task, LogLevel::Warning, 'logs.task.single_action_skipped', $logParams + ['reason' => $reason]);
            } elseif ($buff !== null) {
                $this->log($account, $task, LogLevel::Warning, 'logs.task.step_skipped_buff', $logParams + ['buff' => $buff]);
            } else {
                $this->log($account, $task, LogLevel::Warning, 'logs.task.step_skipped_reason', $logParams + ['reason' => $reason]);
            }

            return StepOutcome::skipped($index, $actionType, $reason, $buff);
        } catch (Throwable $e) {
            $readableError = $e->getMessage();
            $errorPayload = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload(), JSON_THROW_ON_ERROR)
                : $readableError;

            $this->log($account, $task, LogLevel::Error, $manual ? 'logs.task.single_action_failed' : 'logs.task.step_failed', $logParams + ['error' => $readableError]);

            return StepOutcome::failed($index, $actionType, $errorPayload);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function log(Account $account, ScheduledTask $task, LogLevel $level, string $key, array $params): void
    {
        try {
            $this->activityLogger->logTaskEvent($account->id, $task->id, $level, __($key, $params));
        } catch (Throwable) {
            // Observational logging must never escape and cause a command to be replayed.
        }
    }
}
