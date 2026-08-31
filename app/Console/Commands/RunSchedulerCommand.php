<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Psr\SimpleCache\InvalidArgumentException;

class RunSchedulerCommand extends Command
{
    protected $signature = 'tso:run-scheduler
                            {--mode= : Override execution mode (queue|cron|sync)}
                            {--work : Run inline queue worker for tso-tasks and tso-market after scheduling}';

    protected $description = 'Run TSO master scheduler to process Task Planner tasks and Market Analytics atomically.';

    public function __construct(private readonly TaskSchedulerEngine $engine)
    {
        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
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

        $this->engine->recoverStaleTasks();

        $tasksProcessed = $this->engine->processDueTasks($now, $mode);
        $marketProcessed = Artisan::call('tso:sync-market', $mode === 'sync' ? ['--sync' => true] : []) === 0;
        $accountSyncProcessed = $this->engine->processAccountSync($now, $mode);
        $logCleanupProcessed = $this->engine->processLogCleanup($now);

        $this->info("Scheduler cycle completed. Tasks reserved/dispatched: {$tasksProcessed}, Market sync triggered: ".($marketProcessed ? 'Yes' : 'No').', Account sync triggered: '.($accountSyncProcessed > 0 ? "Yes ({$accountSyncProcessed})" : 'No').', Log retention cleanup: '.($logCleanupProcessed ? 'Yes' : 'No'));

        if ($this->option('work')) {
            $this->info('Running inline TSO queue worker (--stop-when-empty --max-time=50)...');
            Artisan::call('queue:work', [
                '--queue' => 'tso-tasks,tso-accounts,tso-market',
                '--stop-when-empty' => true,
                '--max-time' => 50,
                '--max-jobs' => 20,
                '--tries' => 3,
            ]);
            $this->info('Inline TSO queue worker finished.');
        }

        return self::SUCCESS;
    }
}
