<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The whole lifecycle of a scheduled task: planner data, create, update,
 * toggle, delete and manual execution.
 *
 * Validation lives in the form requests, logging lives in TaskActivityLogger,
 * HTTP shaping lives in the controller. This class owns state transitions and
 * nothing else.
 */
final class ScheduledTaskService
{
    private const ACCOUNT_COLUMNS = 'account:id,username,nickname,region,status';

    private const BUSY_STATUSES = ['queued', 'running'];

    public function __construct(private readonly TaskActivityLogger $logger) {}

    public function tasks(): Collection
    {
        return ScheduledTask::with(self::ACCOUNT_COLUMNS)->orderBy('id', 'desc')->get();
    }

    public function accounts(): Collection
    {
        return Account::orderBy('username')->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ScheduledTask
    {
        $task = ScheduledTask::create($attributes);

        $this->logger->scheduled($task);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ScheduledTask $task, array $attributes): ScheduledTask
    {
        $task->update($attributes);

        $this->logger->updated($task);

        return $task->fresh();
    }

    public function toggle(ScheduledTask $task): ScheduledTask
    {
        Log::info(sprintf(
            '[Task] Task #%d is_active %s -> %s via API toggle (status=%s, token=%s)',
            $task->id,
            $task->is_active ? 'true' : 'false',
            $task->is_active ? 'false' : 'true',
            (string) $task->status,
            $task->execution_token ?? 'null'
        ));

        $task->update(['is_active' => ! $task->is_active]);

        $this->logger->toggled($task);

        return $task;
    }

    public function delete(ScheduledTask $task): void
    {
        $taskId = (int) $task->id;
        $taskType = $task->task_type;
        $accountId = $task->account_id;

        $task->delete();

        $this->logger->deleted($accountId, $taskId, $taskType);
    }

    /**
     * Queue a manual run and return the task as the UI should see it.
     *
     * Ручной запуск выполняется даже для выключенной (is_active = false) задачи:
     * кнопка «Run now» — явное действие оператора и важнее флага расписания.
     * Сам флаг не меняется: после ручного запуска задача остаётся на паузе.
     */
    public function execute(ScheduledTask $task): ScheduledTask
    {
        $token = (string) Str::uuid();

        $payload = $task->payload ?? [];
        unset($payload['step_results']);

        $task->update([
            'status' => 'queued',
            'queued_at' => now(),
            'execution_token' => $token,
            'completed_steps' => 0,
            'last_result' => null,
            'payload' => $payload,
        ]);

        Log::info(sprintf(
            '[Task] Manual "Run now" for task #%d [%s] (is_active=%s, schedule=%s, token=%s) — dispatched with force=true',
            $task->id,
            (string) $task->task_type,
            $task->is_active ? 'true' : 'false',
            (string) $task->schedule_type,
            $token
        ));

        ExecuteScheduledTaskJob::dispatch($task->id, $token, true);

        $task->refresh();
        $task->load(self::ACCOUNT_COLUMNS);

        return $task;
    }

    public function withAccount(ScheduledTask $task): ScheduledTask
    {
        $task->load(self::ACCOUNT_COLUMNS);

        return $task;
    }

    public function isBusy(ScheduledTask $task): bool
    {
        return in_array($task->status, self::BUSY_STATUSES, true);
    }
}
