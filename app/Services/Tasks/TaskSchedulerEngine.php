<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Jobs\AccountSyncJob;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\AccountSyncService;
use App\Services\SystemLogCleanupService;
use App\Services\TaskExecutionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Domain engine responsible for task scheduling, atomic reservation, stale recovery, and account sync orchestration.
 */
class TaskSchedulerEngine
{
    public function __construct(
        private readonly TaskExecutionService $taskExecutionService,
        private readonly AccountSyncService $accountSyncService,
        private readonly SystemLogCleanupService $systemLogCleanupService,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Recover tasks stuck in queued or running state longer than stale timeout.
     */
    public function recoverStaleTasks(int $timeoutMinutes = 10): int
    {
        $staleThreshold = Carbon::now()->subMinutes($timeoutMinutes);

        $staleTasks = ScheduledTask::whereIn('status', ['queued', 'running'])
            ->where(function ($query) use ($staleThreshold) {
                $query->where('queued_at', '<=', $staleThreshold)
                    ->orWhereNull('queued_at');
            })
            ->get();

        foreach ($staleTasks as $task) {
            Log::warning("[Scheduler] Resetting stale task #{$task->id} [{$task->task_type}] from status '{$task->status}' back to 'pending'");
            $task->update([
                'status' => 'pending',
                'execution_token' => null,
                'last_result' => "WARNING: Execution timed out / stuck in {$task->status} state.",
            ]);
        }

        return $staleTasks->count();
    }

    /**
     * Check if a task is due for execution.
     */
    public function isTaskDue(ScheduledTask $task, Carbon $now): bool
    {
        if ($task->schedule_type === 'daily' || is_null($task->schedule_type)) {
            if (! $task->run_at_time) {
                return false;
            }

            $currentTime = $now->format('H:i');
            $prevMinute = $now->copy()->subMinute()->format('H:i');
            $runTime = Carbon::parse($task->run_at_time)->format('H:i');

            if ($runTime === $currentTime || $runTime === $prevMinute) {
                return is_null($task->last_run_at) || $task->last_run_at->lt($now->copy()->subMinutes(2));
            }

            return false;
        }

        if ($task->schedule_type === 'once') {
            if ($task->run_at_datetime && is_null($task->last_run_at)) {
                return $now->greaterThanOrEqualTo($task->run_at_datetime);
            }

            return false;
        }

        if ($task->schedule_type === 'interval') {
            $hours = (int) $task->interval_hours;
            $minutes = (int) $task->interval_minutes;
            $intervalTotalMinutes = ($hours * 60) + $minutes;

            if ($intervalTotalMinutes > 0) {
                $baseline = $task->last_run_at ?? $task->created_at;
                if ($baseline) {
                    return $now->diffInMinutes($baseline, false) >= $intervalTotalMinutes
                        || $baseline->diffInMinutes($now, false) >= $intervalTotalMinutes;
                }
            }
        }


        return false;
    }

    /**
     * Perform atomic reservation and dispatch a task.
     */
    public function reserveAndDispatchTask(ScheduledTask $task, string $mode = 'queue'): bool
    {
        $token = (string) Str::uuid();
        $payload = $task->payload ?? [];
        unset($payload['step_results']);

        $reserved = ScheduledTask::where('id', $task->id)
            ->where('is_active', true)
            ->whereIn('status', ['pending', 'completed', 'failed'])
            ->update([
                'status' => 'queued',
                'queued_at' => now(),
                'execution_token' => $token,
                'completed_steps' => 0,
                'payload' => $payload,
            ]);

        if ($reserved === 0) {
            return false;
        }

        if ($mode === 'sync') {
            try {
                $task->refresh();
                $this->taskExecutionService->execute($task, $token);
            } catch (Exception $e) {
                Log::error("Sync execution failed for task #{$task->id}: {$e->getMessage()}");
            }
        } else {
            DB::afterCommit(function () use ($task, $token) {
                Log::info(sprintf(
                    '[Scheduler] Task #%d [%s] reserved and dispatched (schedule=%s, token=%s, queued_at=%s)',
                    $task->id,
                    (string) $task->task_type,
                    (string) $task->schedule_type,
                    $token,
                    now()->toDateTimeString()
                ));

                ExecuteScheduledTaskJob::dispatch($task->id, $token);
            });
        }

        return true;
    }

    /**
     * Evaluate due tasks and perform atomic reservation before dispatching.
     */
    public function processDueTasks(Carbon $now, string $mode = 'queue'): int
    {
        $activeTasks = ScheduledTask::where('is_active', true)
            ->whereIn('status', ['pending', 'completed', 'failed'])
            ->with('account')
            ->get();

        $count = 0;

        foreach ($activeTasks as $task) {
            if ($this->isTaskDue($task, $now)) {
                if ($this->reserveAndDispatchTask($task, $mode)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Evaluate Account Sync and dispatch atomically.
     */
    public function processAccountSync(Carbon $now, string $mode = 'queue'): int
    {
        $syncInterval = (int) Setting::get('sync_interval', 30);

        if ($syncInterval <= 0) {
            return 0;
        }

        $accounts = Account::all();
        $count = 0;

        foreach ($accounts as $account) {
            if ($account->last_sync_at) {
                $elapsedMinutes = (int) $now->diffInMinutes($account->last_sync_at, false);
                if (abs($elapsedMinutes) < $syncInterval) {
                    continue;
                }
            }


            $lockKey = "account_sync_lock:{$account->id}";
            $acquired = $this->cache->add($lockKey, true, 300);

            if (! $acquired) {
                continue;
            }

            $count++;

            if ($mode === 'sync') {
                try {
                    $this->accountSyncService->sync($account);
                } catch (Exception $e) {
                    Log::error("Sync account #{$account->id} failed: {$e->getMessage()}");
                } finally {
                    $this->cache->forget($lockKey);
                }
            } else {
                AccountSyncJob::dispatch($account);
            }
        }

        return $count;

    }

    /**
     * Process auto cleanup for system logs via SystemLogCleanupService.
     */
    public function processLogCleanup(Carbon $now): bool
    {
        return $this->systemLogCleanupService->processAutoCleanup($now);
    }
}
