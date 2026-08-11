<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\LogLevel;
use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskActivityLogger;
use Illuminate\Support\Facades\Log;

final class TaskStateWriter
{
    public function __construct(private readonly TaskActivityLogger $activityLogger) {}

    public function markRunning(ScheduledTask $task, ?array $payload = null): void
    {
        $data = [
            'status' => TaskStatus::Running,
            'queued_at' => now(),
        ];

        if ($payload !== null) {
            $data['payload'] = $payload;
        }

        $task->update($data);
    }

    public function markCompleted(ScheduledTask $task, int $accountId, string $resultSummary, array $payload): void
    {
        $updateData = [
            'status' => TaskStatus::Completed,
            'last_run_at' => now(),
            'last_result' => $resultSummary,
            'payload' => $payload,
            'completed_steps' => 0,
            'execution_token' => null,
        ];

        if ($task->schedule_type === ScheduleType::Once) {
            $updateData['is_active'] = false;
        }

        $task->update($updateData);

        $taskTypeStr = $task->task_type?->value ?? (string) $task->task_type;
        $this->activityLogger->logTaskEvent(
            $accountId,
            $task->id,
            LogLevel::Success,
            __('logs.task.completed', [
                'type' => $taskTypeStr,
                'result' => strlen($resultSummary) > 100 ? substr($resultSummary, 0, 97).'...' : $resultSummary,
            ])
        );
    }

    public function markFailed(ScheduledTask $task, int $accountId, string $errorMsg, array $payload): void
    {
        $updateData = [
            'status' => TaskStatus::Failed,
            'last_run_at' => now(),
            'last_result' => TaskResultSummary::formatSingle($errorMsg, false),
            'payload' => $payload,
            'completed_steps' => 0,
            'execution_token' => null,
        ];

        if ($task->schedule_type === ScheduleType::Once) {
            $updateData['is_active'] = false;
        }

        $task->update($updateData);

        $taskTypeStr = $task->task_type?->value ?? (string) $task->task_type;
        $this->activityLogger->logTaskEvent(
            $accountId,
            $task->id,
            LogLevel::Error,
            __('logs.task.failed', [
                'type' => $taskTypeStr,
                'error' => $errorMsg,
            ])
        );
    }

    public function markSequenceFinished(ScheduledTask $task, int $accountId, string $resultSummary, bool $hasError, array $payload): void
    {
        $nextStatus = $hasError ? TaskStatus::Failed : TaskStatus::Completed;

        $updateData = [
            'status' => $nextStatus,
            'last_run_at' => now(),
            'last_result' => $resultSummary,
            'payload' => $payload,
            'completed_steps' => 0,
            'execution_token' => null,
        ];

        if ($task->schedule_type === ScheduleType::Once) {
            $updateData['is_active'] = false;
            Log::info("[Task] Task #{$task->id} deactivated: schedule_type='once'");
        }

        $task->update($updateData);

        $logLevel = $hasError ? LogLevel::Error : LogLevel::Success;
        $taskTypeStr = $task->task_type?->value ?? (string) $task->task_type;
        $logKey = $hasError ? 'logs.task.completed_with_errors' : 'logs.task.completed';

        $this->activityLogger->logTaskEvent(
            $accountId,
            $task->id,
            $logLevel,
            __($logKey, [
                'type' => $taskTypeStr,
                'result' => strlen($resultSummary) > 100 ? substr($resultSummary, 0, 97).'...' : $resultSummary,
            ])
        );
    }

    public function updateStepProgress(ScheduledTask $task, int $completedSteps, array $payload): void
    {
        $task->update([
            'completed_steps' => $completedSteps,
            'payload' => $payload,
        ]);
    }
}
