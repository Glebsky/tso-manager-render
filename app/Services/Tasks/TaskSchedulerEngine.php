<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Jobs\AccountSyncJob;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\AccountSyncService;
use App\Services\SystemLogCleanupService;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Execution\TaskStateMachine;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

/**
 * Domain engine responsible for task scheduling, atomic reservation, stale recovery, and account sync orchestration.
 */
readonly class TaskSchedulerEngine
{
    /**
     * Statuses a due task may be reserved from. `cancelled` is not terminal:
     * "Stop" only interrupts the current run, the schedule stays (ADR-001).
     *
     * @var list<TaskStatus>
     */
    public const array RESERVABLE_STATUSES = TaskStateMachine::RESERVABLE_STATUSES;

    public function __construct(
        private TaskExecutionService $taskExecutionService,
        private AccountSyncService $accountSyncService,
        private SystemLogCleanupService $systemLogCleanupService,
        private CacheRepository $cache,
        private TaskStateMachine $stateMachine,
    ) {}

    /**
     * Recover tasks whose run stopped sending heartbeats (FR-007).
     *
     * Defaults to `config('game.stale_task_timeout_minutes')`.
     */
    public function recoverStaleTasks(?int $timeoutMinutes = null): int
    {
        $timeoutMinutes ??= max(1, (int) config('game.stale_task_timeout_minutes', 10));
        $staleThreshold = Carbon::now()->subMinutes($timeoutMinutes);

        $staleTasks = TaskStateMachine::staleQuery($staleThreshold)->get();

        $recovered = 0;
        foreach ($staleTasks as $task) {
            if ($this->stateMachine->recoverStale($task, $staleThreshold)) {
                $recovered++;
            }
        }

        return $recovered;
    }

    /**
     * Check if a task is due for execution.
     */
    public function isTaskDue(ScheduledTask $task, Carbon $now): bool
    {
        return match ($task->schedule_type) {
            ScheduleType::Daily => $this->isDailyTaskDue($task, $now),
            ScheduleType::Once => $task->run_at_datetime !== null
                && $task->last_run_at === null
                && $now->greaterThanOrEqualTo($task->run_at_datetime),
            ScheduleType::Interval => $this->isIntervalTaskDue($task, $now),
        };
    }

    private function isDailyTaskDue(ScheduledTask $task, Carbon $now): bool
    {
        if (! $task->run_at_time) {
            return false;
        }

        $timeParts = explode(':', $task->run_at_time);
        $hours = (int) $timeParts[0];
        $minutes = (int) ($timeParts[1] ?? 0);

        $targetToday = $now->copy()->setTime($hours, $minutes);

        if ($now->greaterThanOrEqualTo($targetToday)) {
            return is_null($task->last_run_at) || $task->last_run_at->lt($targetToday);
        }

        return false;
    }

    private function isIntervalTaskDue(ScheduledTask $task, Carbon $now): bool
    {
        $hours = (int) $task->interval_hours;
        $minutes = (int) $task->interval_minutes;
        $intervalTotalMinutes = ($hours * 60) + $minutes;

        if ($intervalTotalMinutes > 0) {
            $baseline = $task->last_run_at ?? $task->created_at;
            if ($baseline) {
                return abs((int) $now->diffInMinutes($baseline)) >= $intervalTotalMinutes;
            }
        }

        return false;
    }

    /**
     * Perform atomic reservation and dispatch a task.
     *
     * @throws Throwable
     */
    public function reserveAndDispatchTask(ScheduledTask $task, string $mode = 'queue'): bool
    {
        $token = (string) Str::uuid();
        $payload = $task->payload ?? [];
        unset($payload['step_results']);

        $reserved = $this->stateMachine->reserve($task, $token, $payload, force: false);

        if (! $reserved) {
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
            DB::afterCommit(static function () use ($task, $token) {
                Log::info(sprintf(
                    '[Scheduler] Task #%d [%s] reserved and dispatched (schedule=%s, token=%s, queued_at=%s)',
                    $task->id,
                    $task->task_type->value,
                    $task->schedule_type->value,
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
     *
     * @throws Throwable
     */
    public function processDueTasks(Carbon $now, string $mode = 'queue'): int
    {
        $activeTasks = ScheduledTask::where('is_active', true)
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->with('account:id,username,nickname,region,status')
            ->get();

        $count = 0;

        foreach ($activeTasks as $task) {
            if ($this->isTaskDue($task, $now) && $this->reserveAndDispatchTask($task, $mode)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Evaluate Account Sync and dispatch atomically.
     *
     * @throws InvalidArgumentException
     */
    public function processAccountSync(Carbon $now, string $mode = 'queue'): int
    {
        $syncInterval = (int) Setting::get('sync_interval', 30);

        if ($syncInterval <= 0) {
            return 0;
        }

        $accounts = Account::query()->select(['id', 'username', 'nickname', 'region', 'status', 'last_sync_at'])->get();
        $count = 0;

        foreach ($accounts as $account) {
            if ($account->status === 'session_expired' || $this->cache->has("account_login_cooldown:{$account->id}")) {
                continue;
            }

            if ($account->last_sync_at) {
                $elapsedMinutes = (int) $now->diffInMinutes($account->last_sync_at);
                if (abs($elapsedMinutes) < $syncInterval) {
                    continue;
                }
            }

            $lockKey = "account_sync_lock:{$account->id}";
            if (! $this->cache->add($lockKey, true, 300)) {
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
