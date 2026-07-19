<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AccountSyncJob;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Jobs\MarketSyncJob;
use App\Models\Account;
use App\Models\MarketSyncLog;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\AccountSyncService;
use App\Services\MarketSyncService;
use App\Services\TaskExecutionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunSchedulerCommand extends Command
{
    protected $signature = 'tso:run-scheduler
                            {--mode= : Override execution mode (queue|cron|sync)}
                            {--work : Run inline queue worker for tso-tasks and tso-market after scheduling}';

    protected $description = 'Run TSO master scheduler to process Task Planner tasks and Market Analytics atomically.';

    private TaskExecutionService $taskExecutionService;

    private MarketSyncService $marketSyncService;

    private AccountSyncService $accountSyncService;

    public function __construct(
        TaskExecutionService $taskExecutionService,
        MarketSyncService $marketSyncService,
        AccountSyncService $accountSyncService
    ) {
        parent::__construct();
        $this->taskExecutionService = $taskExecutionService;
        $this->marketSyncService = $marketSyncService;
        $this->accountSyncService = $accountSyncService;
    }

    public function handle(): int
    {
        $mode = $this->option('mode') ?: config('game.scheduler_mode', 'queue');

        if (! in_array($mode, ['queue', 'cron', 'sync'], true)) {
            $this->error("Invalid mode '{$mode}'. Supported modes: queue, cron, sync.");

            return self::FAILURE;
        }

        if ($mode === 'sync' && $this->option('work')) {
            $this->error('Cannot combine --mode=sync with --work option.');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $this->info("TSO Scheduler started in [{$mode}] mode at {$now->toDateTimeString()}");

        // 1. Recover stale tasks stuck in queued or running status
        $this->recoverStaleTasks();

        // 2. Schedule Task Planner tasks
        $tasksProcessed = $this->processTaskPlanner($now, $mode);

        // 3. Schedule Market Analytics sync
        $marketProcessed = $this->processMarketAnalytics($now, $mode);

        // 4. Schedule Account internal sync based on settings
        $accountSyncProcessed = $this->processAccountSync($now, $mode);

        $this->info("Scheduler cycle completed. Tasks reserved/dispatched: {$tasksProcessed}, Market sync triggered: ".($marketProcessed ? 'Yes' : 'No').', Account sync triggered: '.($accountSyncProcessed > 0 ? "Yes ({$accountSyncProcessed})" : 'No'));

        // 4. If --work flag is specified (or cron mode with work requested), process TSO queues inline
        if ($this->option('work') || ($mode === 'cron' && $this->option('work'))) {
            $this->info('Running inline TSO queue worker (--stop-when-empty --max-time=50)...');
            Artisan::call('queue:work', [
                '--queue' => 'tso-tasks,tso-market',
                '--stop-when-empty' => true,
                '--max-time' => 50,
                '--max-jobs' => 20,
                '--tries' => 3,
            ]);
            $this->info('Inline TSO queue worker finished.');
        }

        return self::SUCCESS;
    }

    /**
     * Recover tasks stuck in queued or running state longer than stale timeout.
     */
    private function recoverStaleTasks(): void
    {
        $timeoutMinutes = (int) config('game.stale_task_timeout_minutes', 10);
        $staleThreshold = Carbon::now()->subMinutes($timeoutMinutes);

        $staleTasks = ScheduledTask::whereIn('status', ['queued', 'running'])
            ->where(function ($query) use ($staleThreshold) {
                $query->where('queued_at', '<=', $staleThreshold)
                    ->orWhereNull('queued_at');
            })
            ->get();

        foreach ($staleTasks as $task) {
            Log::warning("Resetting stale task #{$task->id} [{$task->task_type}] from status '{$task->status}' to 'pending'");
            $task->update([
                'status' => 'pending',
                'execution_token' => null,
                'last_result' => "WARNING: Execution timed out / stuck in {$task->status} state.",
            ]);
        }
    }

    /**
     * Evaluate due tasks and perform atomic reservation before dispatching.
     */
    private function processTaskPlanner(Carbon $now, string $mode): int
    {
        $activeTasks = ScheduledTask::where('is_active', true)
            ->whereIn('status', ['pending', 'completed', 'failed'])
            ->with('account')
            ->get();

        $count = 0;

        foreach ($activeTasks as $task) {
            $shouldRun = false;

            if ($task->schedule_type === 'daily' || is_null($task->schedule_type)) {
                if ($task->run_at_time) {
                    $currentTime = $now->format('H:i');
                    $prevMinute = $now->copy()->subMinute()->format('H:i');
                    $runTime = Carbon::parse($task->run_at_time)->format('H:i');

                    if ($runTime === $currentTime || $runTime === $prevMinute) {
                        if (is_null($task->last_run_at) || $task->last_run_at->lt($now->copy()->subMinutes(2))) {
                            $shouldRun = true;
                        }
                    }
                }
            } elseif ($task->schedule_type === 'once') {
                if ($task->run_at_datetime && is_null($task->last_run_at)) {
                    if ($now->greaterThanOrEqualTo($task->run_at_datetime)) {
                        $shouldRun = true;
                    }
                }
            } elseif ($task->schedule_type === 'interval') {
                $hours = (int) $task->interval_hours;
                $minutes = (int) $task->interval_minutes;
                $intervalTotalMinutes = ($hours * 60) + $minutes;

                if ($intervalTotalMinutes > 0) {
                    $baseline = $task->last_run_at ?? $task->created_at;
                    if ($baseline) {
                        $diffInMinutes = $now->diffInMinutes($baseline);
                        if ($diffInMinutes >= $intervalTotalMinutes) {
                            $shouldRun = true;
                        }
                    }
                }
            }

            if (! $shouldRun) {
                continue;
            }

            // Atomic reservation lock
            $token = (string) Str::uuid();
            $reserved = ScheduledTask::where('id', $task->id)
                ->where('is_active', true)
                ->whereIn('status', ['pending', 'completed', 'failed'])
                ->update([
                    'status' => 'queued',
                    'queued_at' => now(),
                    'execution_token' => $token,
                ]);

            if ($reserved === 0) {
                // Task was concurrently reserved by another process
                continue;
            }

            $count++;
            $this->info("Reserved task #{$task->id} [{$task->task_type}] with token {$token}");

            if ($mode === 'sync') {
                try {
                    $task->refresh();
                    $this->taskExecutionService->execute($task, $token);
                } catch (Exception $e) {
                    $this->error("Sync execution failed for task #{$task->id}: {$e->getMessage()}");
                }
            } else {
                DB::afterCommit(function () use ($task, $token) {
                    ExecuteScheduledTaskJob::dispatch($task->id, $token);
                });
            }
        }

        return $count;
    }

    /**
     * Evaluate Market Analytics sync and dispatch atomically.
     */
    private function processMarketAnalytics(Carbon $now, string $mode): bool
    {
        $accountIdVal = Setting::get('market_account_id');
        if (empty($accountIdVal)) {
            return false;
        }

        $account = Account::find((int) $accountIdVal);
        if (! $account) {
            return false;
        }

        $syncIntervalStr = (string) Setting::get('market_sync_interval', '15');
        $interval = ($syncIntervalStr === 'custom')
            ? (int) Setting::get('market_custom_interval_minutes', 15)
            : (int) $syncIntervalStr;

        if ($interval <= 0) {
            return false;
        }

        $lastLog = MarketSyncLog::where('account_id', $account->id)
            ->where('status', 'SUCCESS')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastLog) {
            $elapsedMinutes = $now->diffInMinutes($lastLog->created_at);
            if ($elapsedMinutes < $interval) {
                return false;
            }
        }

        // Atomic lock check to prevent duplicate dispatches
        $lockKey = "market_sync_lock:{$account->id}";
        $acquired = Cache::lock($lockKey, 180)->get();

        if (! $acquired) {
            $this->info("Market sync lock for account #{$account->id} already held. Skipping.");

            return false;
        }

        $this->info("Triggering Market Analytics sync for account [{$account->username}]...");

        if ($mode === 'sync') {
            try {
                $this->marketSyncService->sync($account);
            } catch (Exception $e) {
                $this->error("Sync market failed: {$e->getMessage()}");
            } finally {
                Cache::forget($lockKey);
            }
        } else {
            DB::afterCommit(function () use ($account) {
                MarketSyncJob::dispatch($account);
            });
        }

        return true;
    }

    /**
     * Evaluate Account Sync and dispatch atomically.
     */
    private function processAccountSync(Carbon $now, string $mode): int
    {
        $syncInterval = (int) Setting::get('sync_interval', 30);

        if ($syncInterval <= 0) {
            return 0;
        }

        $accounts = Account::all();
        $count = 0;

        foreach ($accounts as $account) {
            if ($account->last_sync_at) {
                $elapsedMinutes = $now->diffInMinutes($account->last_sync_at);
                if ($elapsedMinutes < $syncInterval) {
                    continue;
                }
            }

            // Atomic lock check to prevent duplicate dispatches
            $lockKey = "account_sync_lock:{$account->id}";
            $acquired = Cache::lock($lockKey, 300)->get();

            if (! $acquired) {
                $this->info("Account sync lock for account #{$account->id} already held. Skipping.");

                continue;
            }

            $this->info("Triggering Account Sync for account [{$account->username}]...");
            $count++;

            if ($mode === 'sync') {
                try {
                    $this->accountSyncService->sync($account);
                } catch (Exception $e) {
                    $this->error("Sync account #{$account->id} failed: {$e->getMessage()}");
                } finally {
                    Cache::lock($lockKey)->forceRelease();
                }
            } else {
                DB::afterCommit(function () use ($account) {
                    AccountSyncJob::dispatch($account);
                });
            }
        }

        return $count;
    }
}
