<?php

declare(strict_types=1);

namespace App\Services\Tasks;

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
use App\Services\Tasks\Scheduling\ScheduleCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
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
        private ScheduleCalculator $calculator,
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
     * Whether the task's slot has been reached (`next_run_at <= now`, FR-006).
     *
     * "Due" only means the slot is reached; {@see processDueTasks} then decides
     * whether it is still on time or already missed (ADR-002).
     */
    public function isTaskDue(ScheduledTask $task, Carbon $now): bool
    {
        $slot = $this->slotOf($task, $now);

        return $slot !== null && $slot->lessThanOrEqualTo($now);
    }

    /**
     * Persisted `next_run_at`, or the slot derived from the legacy fields for
     * rows that do not have one yet (self-healing, see processDueTasks).
     */
    private function slotOf(ScheduledTask $task, Carbon $now): ?CarbonImmutable
    {
        if ($task->next_run_at !== null) {
            return CarbonImmutable::instance($task->next_run_at);
        }

        return $this->calculator->initialRunAt($task, $now);
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
     * Reserve every task whose slot is reached and still on time; skip the
     * slots the scheduler arrived too late for (FR-006, FR-010, ADR-002).
     *
     * The selection is an indexed query on `(is_active, next_run_at)` that
     * does not load `payload` (NFR-001). Rows without `next_run_at` (created
     * before the column existed or outside the service) are healed in place.
     *
     * @return int number of tasks reserved (skipped slots are not counted)
     *
     * @throws Throwable
     */
    public function processDueTasks(Carbon $now, string $mode = 'queue'): int
    {
        $graceMinutes = max(0, (int) (config('game.scheduler.missed_grace_minutes') ?? 5));

        $dueTasks = ScheduledTask::query()
            ->select(self::DUE_COLUMNS)
            ->where('is_active', true)
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->where(function ($query) use ($now) {
                $query->where('next_run_at', '<=', $now)
                    ->orWhereNull('next_run_at');
            })
            ->orderBy('next_run_at')
            ->get();

        $count = 0;

        foreach ($dueTasks as $task) {
            $slot = $this->healMissingSlot($task, $now);

            if ($slot === null || $slot->greaterThan($now)) {
                continue;
            }

            if ($this->calculator->isMissed($slot, $now, $graceMinutes)) {
                $this->stateMachine->skipMissedSlot($task, $slot, $this->calculator->nextFutureSlot($task, $now));

                continue;
            }

            $fullTask = ScheduledTask::query()
                ->with('account:id,username,nickname,region,status')
                ->find($task->id);

            if ($fullTask !== null && $this->reserveAndDispatchTask($fullTask, $mode)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Columns needed to decide whether a task is due — everything but `payload`.
     *
     * @var list<string>
     */
    private const array DUE_COLUMNS = [
        'id', 'name', 'account_id', 'task_type', 'schedule_type', 'run_at_time', 'run_at_datetime',
        'interval_hours', 'interval_minutes', 'next_run_at', 'is_active', 'status',
        'last_run_at', 'created_at',
    ];

    /**
     * Returns the task's slot; when `next_run_at` is missing, derives it from
     * the legacy fields and persists it (CAS on `next_run_at IS NULL`).
     */
    private function healMissingSlot(ScheduledTask $task, Carbon $now): ?CarbonImmutable
    {
        if ($task->next_run_at !== null) {
            return CarbonImmutable::instance($task->next_run_at);
        }

        $slot = $this->calculator->initialRunAt($task, $now);

        if ($slot === null) {
            return null;
        }

        ScheduledTask::whereKey($task->id)
            ->whereNull('next_run_at')
            ->update(['next_run_at' => $slot]);

        $task->next_run_at = Carbon::instance($slot);

        Log::info(sprintf('[Scheduler] Task #%d had no next_run_at; derived %s', $task->id, $slot->toDateTimeString()));

        return $slot;
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
