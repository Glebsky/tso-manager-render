<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExecuteScheduledTasks extends Command
{
    protected $signature = 'tso:execute-tasks 
                            {--task= : Run a specific task ID directly} 
                            {--async : Dispatch tasks to queue instead of running synchronously}';

    protected $description = 'Execute scheduled TSO tasks (legacy wrapper).';

    private TaskExecutionService $executionService;

    public function __construct(TaskExecutionService $executionService)
    {
        parent::__construct();
        $this->executionService = $executionService;
    }

    public function handle(): int
    {
        $now = Carbon::now();
        $singleTaskId = $this->option('task');
        $isAsync = (bool) $this->option('async');

        if ($singleTaskId) {
            $task = ScheduledTask::with('account')->find($singleTaskId);
            if (! $task) {
                $this->error("Task #{$singleTaskId} not found.");

                return self::FAILURE;
            }

            return $this->runTask($task, $isAsync);
        }

        $activeTasks = ScheduledTask::where('is_active', true)
            ->whereIn('status', ['pending', 'completed', 'failed'])
            ->with('account')
            ->get();

        $tasksToRun = [];

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

            if ($shouldRun) {
                $tasksToRun[] = $task;
            }
        }

        if (empty($tasksToRun)) {
            $this->info("No tasks to run at {$now->toDateTimeString()}.");

            return self::SUCCESS;
        }

        foreach ($tasksToRun as $task) {
            $this->runTask($task, $isAsync);
        }

        $this->info('Processed '.count($tasksToRun).' task(s).');

        return self::SUCCESS;
    }

    private function runTask(ScheduledTask $task, bool $isAsync): int
    {
        $account = $task->account;
        $this->info("Running task #{$task->id} [{$task->task_type}] for account [{$account->username}]");

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
            $this->info("  → Task #{$task->id} is already reserved/running.");

            return self::SUCCESS;
        }

        if ($isAsync) {
            ExecuteScheduledTaskJob::dispatch($task->id, $token);
            $this->info('  → Dispatched to queue.');

            return self::SUCCESS;
        }

        try {
            $task->refresh();
            $result = $this->executionService->execute($task, $token);
            $this->info("  → Success: {$result}");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("  → Failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
