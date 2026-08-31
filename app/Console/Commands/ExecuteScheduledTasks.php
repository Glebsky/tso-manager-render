<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ScheduledTask;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExecuteScheduledTasks extends Command
{
    protected $signature = 'tso:execute-tasks
                            {--task= : Run a specific task ID directly}
                            {--async : Dispatch tasks to queue instead of running synchronously}';

    protected $description = 'Execute scheduled TSO tasks (legacy CLI wrapper).';

    public function __construct(private readonly TaskSchedulerEngine $engine)
    {
        parent::__construct();
    }

    /**
     * @throws \Throwable
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $singleTaskId = $this->option('task');
        $isAsync = (bool) $this->option('async');
        $mode = $isAsync ? 'queue' : 'sync';

        if ($singleTaskId) {
            if (! is_numeric($singleTaskId)) {
                $this->error("Invalid task ID: {$singleTaskId}");

                return self::FAILURE;
            }

            $task = ScheduledTask::with('account')->find((int) $singleTaskId);
            if (! $task) {
                $this->error("Task #{$singleTaskId} not found.");

                return self::FAILURE;
            }

            $account = $task->account;
            $username = $account->username ?? 'unknown';
            $this->info("Running task #{$task->id} [{$task->task_type->value}] for account [{$username}]");
            if (! $this->engine->reserveAndDispatchTask($task, $mode)) {
                $this->info("  → Task #{$task->id} is already reserved/running.");
            }

            return self::SUCCESS;
        }

        $count = $this->engine->processDueTasks($now, $mode);
        $this->info("Processed {$count} task(s) at {$now->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
