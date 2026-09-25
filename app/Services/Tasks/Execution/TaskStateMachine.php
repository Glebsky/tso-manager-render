<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\LogLevel;
use App\Enums\ScheduleType;
use App\Enums\TaskResultPrefix;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskActivityLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final readonly class TaskStateMachine
{
    public const array RESERVABLE_STATUSES = [
        TaskStatus::Pending,
        TaskStatus::Completed,
        TaskStatus::Failed,
        TaskStatus::Cancelled,
    ];

    public function __construct(private TaskActivityLogger $activityLogger) {}

    /**
     * Atomically reserve a task for execution (pending|completed|failed|cancelled -> queued).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function reserve(ScheduledTask $task, string $token, ?array $payload = null, bool $force = false): bool
    {
        $query = ScheduledTask::whereKey($task->id);

        if ($force) {
            // Manual run now: can run paused tasks, but cannot run currently busy tasks.
            $query->whereNotIn('status', [TaskStatus::Queued, TaskStatus::Running]);
        } else {
            // Scheduled run: requires is_active=true and one of the reservable statuses.
            $query->where('is_active', true)
                ->whereIn('status', self::RESERVABLE_STATUSES);
        }

        $cleanPayload = $payload ?? ($task->payload ?? []);
        unset($cleanPayload['step_results']);

        $updated = $query->update([
            'status' => TaskStatus::Queued,
            'queued_at' => now(),
            'heartbeat_at' => now(),
            'execution_token' => $token,
            'completed_steps' => 0,
            'last_result' => null,
            'payload' => $cleanPayload,
        ]) === 1;

        $tokenPrefix = substr($token, 0, 8);

        if ($updated) {
            Log::info(sprintf(
                '[TaskStateMachine] Task #%d reserved: %s -> queued (schedule=%s, token_prefix=%s, force=%s)',
                $task->id,
                $task->status->value,
                $task->schedule_type->value,
                $tokenPrefix,
                $force ? 'true' : 'false'
            ));
        } else {
            Log::warning(sprintf(
                '[TaskStateMachine] Task #%d reserve CAS failed: status=%s, is_active=%s, force=%s',
                $task->id,
                $task->status->value,
                $task->is_active ? 'true' : 'false',
                $force ? 'true' : 'false'
            ));
        }

        return $updated;
    }

    /**
     * Atomically start execution of a reserved task (queued -> running).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function start(ScheduledTask $task, string $token, ?array $payload = null): bool
    {
        $query = ScheduledTask::whereKey($task->id)
            ->where('execution_token', $token)
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running]);

        $data = [
            'status' => TaskStatus::Running,
            'heartbeat_at' => now(),
        ];

        if ($payload !== null) {
            $data['payload'] = $payload;
        }

        $updated = $query->update($data) === 1;
        $tokenPrefix = substr($token, 0, 8);

        if ($updated) {
            Log::info(sprintf(
                '[TaskStateMachine] Task #%d started: %s -> running (token_prefix=%s)',
                $task->id,
                $task->status->value,
                $tokenPrefix
            ));
        } else {
            Log::warning(sprintf(
                '[TaskStateMachine] Task #%d start CAS failed: expected token %s (current status=%s)',
                $task->id,
                $tokenPrefix,
                $task->status->value
            ));
        }

        return $updated;
    }

    /**
     * Atomically update progress of a sequence task (completed_steps and payload).
     *
     * @param  array<string, mixed>  $payload
     */
    public function progress(ScheduledTask $task, string $token, int $completedSteps, array $payload): bool
    {
        $updated = ScheduledTask::whereKey($task->id)
            ->where('execution_token', $token)
            ->where('status', TaskStatus::Running)
            ->update([
                'completed_steps' => $completedSteps,
                'payload' => $payload,
                'heartbeat_at' => now(),
            ]) === 1;

        if (! $updated) {
            Log::warning(sprintf(
                '[TaskStateMachine] Task #%d progress CAS failed: token=%s, step=%d',
                $task->id,
                substr($token, 0, 8),
                $completedSteps
            ));
        }

        return $updated;
    }

    /**
     * Refresh the liveness timestamp of a run (FR-007).
     *
     * `$until` lets a process that intentionally pauses (delay between steps,
     * hand-off to a delayed job) push the heartbeat into the future so stale
     * recovery does not reset a healthy run. Returns false when this token no
     * longer owns the task — the caller must stop working.
     */
    public function heartbeat(ScheduledTask $task, string $token, ?Carbon $until = null): bool
    {
        $updated = ScheduledTask::whereKey($task->id)
            ->where('execution_token', $token)
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running])
            ->update(['heartbeat_at' => $until ?? now()]) === 1;

        if (! $updated) {
            Log::warning(sprintf(
                '[TaskStateMachine] Task #%d heartbeat CAS failed: token=%s',
                $task->id,
                substr($token, 0, 8)
            ));
        }

        return $updated;
    }

    /**
     * Atomically finish task execution ((running|queued) -> completed|failed).
     *
     * @param  array<string, mixed>  $payload
     */
    public function finish(
        ScheduledTask $task,
        string $token,
        TaskStatus $to,
        string $summary,
        array $payload,
        ?int $accountId = null
    ): bool {
        $accountId = $accountId ?? $task->account_id;
        $isActive = $task->schedule_type === ScheduleType::Once ? false : $task->is_active;

        $updated = ScheduledTask::whereKey($task->id)
            ->where('execution_token', $token)
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running])
            ->update([
                'status' => $to,
                'last_run_at' => now(),
                'last_result' => $summary,
                'payload' => $payload,
                'completed_steps' => 0,
                'execution_token' => null,
                'is_active' => $isActive,
            ]) === 1;

        $tokenPrefix = substr($token, 0, 8);

        if ($updated) {
            Log::info(sprintf(
                '[TaskStateMachine] Task #%d finished: -> %s (token_prefix=%s)',
                $task->id,
                $to->value,
                $tokenPrefix
            ));

            $logLevel = $to === TaskStatus::Completed ? LogLevel::Success : LogLevel::Error;
            $taskTypeStr = $task->task_type->value;
            $isSequence = $task->task_type === TaskType::Sequence;
            $logKey = match ($to) {
                TaskStatus::Completed => 'logs.task.completed',
                TaskStatus::Failed => $isSequence ? 'logs.task.completed_with_errors' : 'logs.task.failed',
                default => 'logs.task.completed',
            };

            $this->activityLogger->logTaskEvent(
                $accountId,
                $task->id,
                $logLevel,
                __($logKey, [
                    'type' => $taskTypeStr,
                    'result' => $summary,
                    'error' => $summary,
                ])
            );
        } else {
            Log::warning(sprintf(
                '[TaskStateMachine] Task #%d finish CAS failed (target=%s, token_prefix=%s)',
                $task->id,
                $to->value,
                $tokenPrefix
            ));
        }

        return $updated;
    }

    /**
     * Atomically cancel a task run (ADR-001).
     *
     * If task was busy (queued or running), cancel sets last_run_at = now() (ADR-003).
     * If task was idle, cancel does NOT shift the schedule.
     */
    public function cancel(ScheduledTask $task, string $stopMessage): bool
    {
        $formattedMessage = TaskResultPrefix::Skipped->format($stopMessage);
        $isActive = $task->schedule_type === ScheduleType::Once ? false : $task->is_active;

        // 1. If currently busy, cancel and mark last_run_at = now()
        $updated = ScheduledTask::whereKey($task->id)
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running])
            ->update([
                'status' => TaskStatus::Cancelled,
                'execution_token' => null,
                'last_run_at' => now(),
                'last_result' => $formattedMessage,
                'is_active' => $isActive,
            ]) === 1;

        // 2. If idle, cancel without shifting last_run_at
        if (! $updated) {
            $updated = ScheduledTask::whereKey($task->id)
                ->whereNotIn('status', [TaskStatus::Queued, TaskStatus::Running])
                ->update([
                    'status' => TaskStatus::Cancelled,
                    'execution_token' => null,
                    'last_result' => $formattedMessage,
                    'is_active' => $isActive,
                ]) === 1;
        }

        if ($updated) {
            Log::info("[TaskStateMachine] Task #{$task->id} cancelled: -> cancelled");

            $totalSteps = count($task->payload['actions'] ?? []);
            $completedSteps = $task->completed_steps ?? 0;

            $this->activityLogger->logTaskEvent(
                $task->account_id,
                $task->id,
                LogLevel::Warning,
                __('logs.task.stopped_by_user', [
                    'id' => $task->id,
                    'step' => $completedSteps,
                    'total' => $totalSteps > 0 ? $totalSteps : 1,
                ])
            );
        }

        return $updated;
    }

    /**
     * Handle job failure callback from queue worker.
     */
    public function failJob(ScheduledTask $task, string $token, string $error): bool
    {
        $summary = TaskResultSummary::formatSingle($error, false);
        $isActive = $task->schedule_type === ScheduleType::Once ? false : $task->is_active;

        $updated = ScheduledTask::whereKey($task->id)
            ->where('execution_token', $token)
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running])
            ->update([
                'status' => TaskStatus::Failed,
                'execution_token' => null,
                'last_run_at' => now(),
                'last_result' => $summary,
                'completed_steps' => 0,
                'is_active' => $isActive,
            ]) === 1;

        if ($updated) {
            Log::error("[TaskStateMachine] Job for task #{$task->id} failed with token {$token}: {$error}");

            $this->activityLogger->logTaskEvent(
                $task->account_id,
                $task->id,
                LogLevel::Error,
                __('logs.task.failed', [
                    'type' => $task->task_type->value,
                    'error' => $error,
                ])
            );
        } else {
            Log::info("[TaskStateMachine] Job for task #{$task->id} failed but token {$token} no longer owns the task; skipping state update");
        }

        return $updated;
    }

    /**
     * Recover a stale task stuck in queued or running state (FR-007).
     *
     * A run is stale when its last heartbeat (falling back to `queued_at` for
     * rows created before heartbeats existed) is older than the threshold.
     * Recurring tasks go back to `pending` and are picked up by the schedule;
     * a once task goes to `failed` — its side effects may already have
     * happened and it must never run twice.
     */
    public function recoverStale(ScheduledTask $task, Carbon $threshold): bool
    {
        $isOnce = $task->schedule_type === ScheduleType::Once;
        $toStatus = $isOnce ? TaskStatus::Failed : TaskStatus::Pending;
        $message = "Execution timed out / stuck in {$task->status->value} state.";

        $updated = self::staleQuery($threshold)
            ->whereKey($task->id)
            ->update([
                'status' => $toStatus,
                'execution_token' => null,
                'heartbeat_at' => null,
                'last_run_at' => now(),
                'last_result' => $isOnce
                    ? TaskResultPrefix::Error->format($message)
                    : TaskResultPrefix::Warning->format($message),
                'is_active' => $isOnce ? false : $task->is_active,
            ]) === 1;

        if ($updated) {
            Log::warning(sprintf(
                '[TaskStateMachine] Stale task #%d recovered: %s -> %s (last heartbeat=%s, threshold=%s)',
                $task->id,
                $task->status->value,
                $toStatus->value,
                ($task->heartbeat_at ?? $task->queued_at)?->toDateTimeString() ?? 'null',
                $threshold->toDateTimeString()
            ));

            $this->activityLogger->logTaskEvent(
                $task->account_id,
                $task->id,
                $isOnce ? LogLevel::Error : LogLevel::Warning,
                __('logs.task.stale_recovered', [
                    'name' => $task->name,
                    'status' => $toStatus->value,
                ])
            );
        }

        return $updated;
    }

    /**
     * Busy tasks whose liveness timestamp is older than the threshold.
     *
     * @return Builder<ScheduledTask>
     */
    public static function staleQuery(Carbon $threshold): Builder
    {
        return ScheduledTask::query()
            ->whereIn('status', [TaskStatus::Queued, TaskStatus::Running])
            ->where(function (Builder $query) use ($threshold) {
                $query->where('heartbeat_at', '<=', $threshold)
                    ->orWhere(function (Builder $query) use ($threshold) {
                        $query->whereNull('heartbeat_at')
                            ->where(function (Builder $query) use ($threshold) {
                                $query->where('queued_at', '<=', $threshold)
                                    ->orWhereNull('queued_at');
                            });
                    });
            });
    }
}
