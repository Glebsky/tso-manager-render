<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\LogLevel;
use App\Enums\TaskType;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Support\Security\CredentialRedactor;

/**
 * Writes the task audit trail.
 *
 * The BotLog::create() call carrying the task prefix was repeated six times
 * across the controller. The prefix, the level and the shape of the entry now
 * have exactly one definition.
 */
final class TaskActivityLogger
{
    public function scheduled(ScheduledTask $task): void
    {
        $this->log($task->account_id, (int) $task->id, __('logs.task.scheduled', [
            'id' => $task->id,
            'type' => $task->task_type instanceof TaskType ? $task->task_type->value : $task->task_type,
            'schedule' => $task->schedule_type?->value ?? $task->schedule_type,
        ]));
    }

    public function updated(ScheduledTask $task): void
    {
        $this->log($task->account_id, (int) $task->id, __('logs.task.updated', [
            'id' => $task->id,
            'type' => $task->task_type instanceof TaskType ? $task->task_type->value : $task->task_type,
        ]));
    }

    public function toggled(ScheduledTask $task): void
    {
        $key = $task->is_active ? 'logs.task.enabled' : 'logs.task.disabled';

        $this->log($task->account_id, (int) $task->id, __($key, [
            'id' => $task->id,
            'type' => $task->task_type instanceof TaskType ? $task->task_type->value : $task->task_type,
        ]));
    }

    public function deleted(?int $accountId, int $taskId, TaskType|string|null $taskType): void
    {
        $this->log($accountId, $taskId, __('logs.task.deleted', [
            'id' => $taskId,
            'type' => $taskType instanceof TaskType ? $taskType->value : $taskType,
        ]));
    }

    private function log(?int $accountId, int $taskId, string $message): void
    {
        BotLog::create([
            'account_id' => $accountId,
            'level' => LogLevel::Info,
            'message' => "[Task][Task#{$taskId}] ".CredentialRedactor::redact($message),
        ]);
    }
}
