<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\TaskAccountNotFoundException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Models\Account;
use App\Models\ScheduledTask;
use Illuminate\Support\Facades\Log;

final class TaskExecutionGuard
{
    public function ensureCanExecute(ScheduledTask $task, ?string $expectedToken = null, bool $force = false): Account
    {
        $task->refresh();

        if (! $task->is_active && ! $force) {
            $this->logInactive($task, $expectedToken);

            throw new TaskInactiveException($task->id);
        }

        if (! $task->is_active) {
            Log::info("[Task] Task #{$task->id} is paused; executing anyway because force=true (manual run)");
        }

        if ($expectedToken !== null && $task->execution_token !== null && $task->execution_token !== $expectedToken) {
            throw new TokenMismatchException($task->id, $expectedToken, $task->execution_token);
        }

        $account = $task->account;
        if (! $account) {
            throw new TaskAccountNotFoundException($task->id);
        }

        return $account;
    }

    private function logInactive(ScheduledTask $task, ?string $expectedToken): void
    {
        Log::warning(sprintf(
            '[Task] Task #%d rejected because is_active=false. state: type=%s status=%s schedule=%s token=%s expected_token=%s completed_steps=%s queued_at=%s last_run_at=%s updated_at=%s last_result=%s',
            $task->id,
            $task->task_type->value,
            $task->status->value,
            $task->schedule_type->value,
            $task->execution_token ?? 'null',
            $expectedToken ?? 'null',
            (string) $task->completed_steps,
            $task->queued_at?->toDateTimeString() ?? 'null',
            $task->last_run_at?->toDateTimeString() ?? 'null',
            $task->updated_at?->toDateTimeString() ?? 'null',
            (string) ($task->last_result ?? 'null')
        ));
    }
}
