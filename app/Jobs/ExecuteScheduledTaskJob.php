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

    /**
     * Seconds of work budget per job invocation. Kept short so the inline
     * cron worker (queue:work --max-time=50) is not blocked by one job.
     */
    private const TIME_BUDGET_SECONDS = 45;

    /**
     * Safety margin for a single step's own duration when deciding whether
     * to continue in-process or hand off to a delayed job.
     */
    private const STEP_MARGIN_SECONDS = 10;

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

        if ($task->status !== 'queued' && $task->status !== 'running') {
            Log::info("ExecuteScheduledTaskJob: Task #{$this->taskId} status is '{$task->status}' (not queued). Skipping.");

            return;
        }

        if ($task->execution_token !== null && $task->execution_token !== $this->executionToken) {
            Log::warning("ExecuteScheduledTaskJob: Execution token mismatch for Task #{$this->taskId}. Expected {$this->executionToken}, found {$task->execution_token}. Skipping.");

            return;
        }

        if (! $task->is_active && $task->execution_token === null) {
            Log::info("ExecuteScheduledTaskJob: Task #{$this->taskId} is no longer active. Resetting status to pending.");
            $task->update(['status' => 'pending']);

            return;
        }

        Log::info("Executing Task #{$this->taskId} via ExecuteScheduledTaskJob (attempt {$this->attempts()})");

        // Non-sequence tasks are a single action: run them as before.
        if ($task->task_type !== 'sequence') {
            $executionService->execute($task, $this->executionToken);

            return;
        }

        // Sequence tasks are executed step-by-step. Short delays are waited
        // out inline within the time budget; long delays (or an exhausted
        // budget) hand the remaining steps to a fresh delayed job so no
        // single run blocks the worker or hits execution time limits.
        $budgetEndsAt = microtime(true) + self::TIME_BUDGET_SECONDS;

        while (true) {
            $state = $executionService->executeSequenceStep($task, $this->executionToken);

            if ($state['finished']) {
                return;
            }

            $delay = max(0, (int) $state['nextDelay']);

            if (microtime(true) + $delay + self::STEP_MARGIN_SECONDS < $budgetEndsAt) {
                if ($delay > 0) {
                    sleep($delay);
                }

                continue;
            }

            // Push the stale-recovery heartbeat past the intentional wait so
            // recoverStaleTasks() does not reset a healthy paused run.
            $task->update(['queued_at' => now()->addSeconds($delay)]);

            self::dispatch($this->taskId, $this->executionToken)
                ->delay(now()->addSeconds(max($delay, 1)));

            Log::info("Task #{$this->taskId}: handed off to a delayed job (next step in {$delay}s, completed_steps={$task->completed_steps}).");

            return;
        }
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
