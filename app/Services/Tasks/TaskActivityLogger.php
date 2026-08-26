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
        $this->log($task->account_id, $task->id, __('logs.task.scheduled', [
            'id' => $task->id,
            'type' => $task->task_type->value,
            'schedule' => $task->schedule_type->value,
        ]));
    }

    public function updated(ScheduledTask $task): void
    {
        $this->log($task->account_id, $task->id, __('logs.task.updated', [
            'id' => $task->id,
            'type' => $task->task_type->value,
        ]));
    }

    public function toggled(ScheduledTask $task): void
    {
        $key = $task->is_active ? 'logs.task.enabled' : 'logs.task.disabled';

        $this->log($task->account_id, $task->id, __($key, [
            'id' => $task->id,
            'type' => $task->task_type->value,
        ]));
    }

    public function deleted(?int $accountId, int $taskId, TaskType|string|null $taskType): void
    {
        $this->log($accountId, $taskId, __('logs.task.deleted', [
            'id' => $taskId,
            'type' => $taskType instanceof TaskType ? $taskType->value : $taskType,
        ]));
    }

    public function logTaskEvent(?int $accountId, int $taskId, LogLevel|string $level, string $message): void
    {
        $logLevel = $level instanceof LogLevel ? $level : LogLevel::from((string) $level);

        BotLog::create([
            'account_id' => $accountId,
            'level' => $logLevel,
            'message' => CredentialRedactor::redact("[Task][Task#{$taskId}] {$message}"),
        ]);
    }

    private function log(?int $accountId, int $taskId, string $message): void
    {
        $this->logTaskEvent($accountId, $taskId, LogLevel::Info, $message);
    }
}
