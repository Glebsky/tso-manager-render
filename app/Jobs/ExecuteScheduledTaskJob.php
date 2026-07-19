<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteScheduledTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public int $taskId;

    public string $executionToken;

    /**
     * Create a new job instance.
     */
    public function __construct(int $taskId, string $executionToken)
    {
        $this->taskId = $taskId;
        $this->executionToken = $executionToken;
        $this->onQueue('tso-tasks');
    }

    /**
     * Execute the job.
     */
    public function handle(TaskExecutionService $executionService): void
    {
        $task = ScheduledTask::find($this->taskId);

        if (! $task) {
            Log::warning("ExecuteScheduledTaskJob: Task #{$this->taskId} not found. Skipping.");

            return;
        }

        if (! $task->is_active) {
            Log::info("ExecuteScheduledTaskJob: Task #{$this->taskId} is no longer active. Skipping.");

            return;
        }

        if ($task->status !== 'queued' && $task->status !== 'running') {
            Log::info("ExecuteScheduledTaskJob: Task #{$this->taskId} status is '{$task->status}' (not queued). Skipping.");

            return;
        }

        if ($task->execution_token !== null && $task->execution_token !== $this->executionToken) {
            Log::warning("ExecuteScheduledTaskJob: Execution token mismatch for Task #{$this->taskId}. Expected {$this->executionToken}, found {$task->execution_token}. Skipping.");

            return;
        }

        Log::info("Executing Task #{$this->taskId} via ExecuteScheduledTaskJob (attempt {$this->attempts()})");
        $executionService->execute($task, $this->executionToken);
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ExecuteScheduledTaskJob failed permanently for task #{$this->taskId}: {$exception->getMessage()}");

        $task = ScheduledTask::find($this->taskId);
        if ($task) {
            $task->update([
                'status' => 'failed',
                'last_run_at' => now(),
                'last_result' => 'FAILED: '.$exception->getMessage(),
                'execution_token' => null,
            ]);

            BotLog::create([
                'account_id' => $task->account_id,
                'level' => 'error',
                'message' => "Job for scheduled task #{$task->id} [{$task->task_type}] failed after all retries: {$exception->getMessage()}",
            ]);
        }
    }
}
