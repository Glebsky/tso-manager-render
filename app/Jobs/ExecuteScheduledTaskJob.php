<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LogLevel;
use App\Enums\TaskResultPrefix;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
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

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Seconds of work budget per job invocation. Kept short so the inline
     * cron worker (queue:work --max-time=50) is not blocked by one job.
     */
    private const int TIME_BUDGET_SECONDS = 45;

    /**
     * Safety margin for a single step's own duration when deciding whether
     * to continue in-process or hand off to a delayed job.
     */
    private const int STEP_MARGIN_SECONDS = 10;

    public int $taskId;

    public string $executionToken;

    /**
     * Manual "Run now" runs ignore the is_active flag: a paused task must still
     * execute when the operator presses the button explicitly. The scheduler
     * never sets this flag.
     */
    public bool $force;

    /**
     * Create a new job instance.
     */
    public function __construct(int $taskId, string $executionToken, bool $force = false)
    {
        $this->taskId = $taskId;
        $this->executionToken = $executionToken;
        $this->force = $force;
        $this->onQueue('tso-tasks');
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(TaskExecutionService $executionService): void
    {
        $task = ScheduledTask::find($this->taskId);

        if (! $task) {
            Log::warning("[TaskJob] Task #{$this->taskId} not found; skipping");

            return;
        }

        if ($task->status !== TaskStatus::Queued && $task->status !== TaskStatus::Running) {
            Log::info("[TaskJob] Task #{$this->taskId} has status '{$task->status->value}' instead of 'queued'; skipping");

            return;
        }

        if ($task->execution_token !== null && $task->execution_token !== $this->executionToken) {
            Log::warning("[TaskJob] Execution token mismatch for task #{$this->taskId} (expected {$this->executionToken}, found {$task->execution_token}); skipping");

            return;
        }

        if (! $task->is_active && ! $this->force) {
            Log::warning(sprintf(
                '[TaskJob] Task #%d is not active at execution time; skipping. %s',
                $this->taskId,
                $this->describeTask($task)
            ));

            $task->update([
                'status' => TaskStatus::Pending,
                'execution_token' => null,
                'last_result' => TaskResultPrefix::Skipped->format('task was paused before execution.'),
            ]);

            return;
        }

        Log::info(sprintf(
            '[TaskJob] Executing task #%d (attempt %d, %s). %s',
            $this->taskId,
            $this->attempts(),
            $this->force ? 'manual run, forced' : 'scheduled',
            $this->describeTask($task)
        ));

        if (! $task->is_active) {
            Log::info("[TaskJob] Task #{$this->taskId} is paused, but runs anyway because this is a manual \"Run now\" execution");
        }

        // Non-sequence tasks are a single action: run them as before.
        if ($task->task_type !== TaskType::Sequence) {
            $executionService->execute($task, $this->executionToken, $this->force);

            return;
        }

        // Sequence tasks are executed step-by-step. Short delays are waited
        // out inline within the time budget; long delays (or an exhausted
        // budget) hand the remaining steps to a fresh delayed job so no
        // single run blocks the worker or hits execution time limits.
        $budgetEndsAt = microtime(true) + self::TIME_BUDGET_SECONDS;

        while (true) {
            $state = $executionService->executeSequenceStep($task, $this->executionToken, $this->force);

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

            self::dispatch($this->taskId, $this->executionToken, $this->force)
                ->delay(now()->addSeconds(max($delay, 1)));

            Log::info("[TaskJob] Task #{$this->taskId} handed off to a delayed job (next step in {$delay}s, completed steps: {$task->completed_steps})");

            return;
        }
    }

    /**
     * Diagnostic snapshot of the task state, used in every [TaskJob] log line.
     */
    private function describeTask(ScheduledTask $task): string
    {
        return sprintf(
            'state: is_active=%s status=%s schedule=%s token=%s job_token=%s completed_steps=%s queued_at=%s last_run_at=%s updated_at=%s',
            $task->is_active ? 'true' : 'false',
            $task->status->value,
            $task->schedule_type->value,
            $task->execution_token ?? 'null',
            $this->executionToken,
            $task->completed_steps,
            $task->queued_at?->toDateTimeString() ?? 'null',
            $task->last_run_at?->toDateTimeString() ?? 'null',
            $task->updated_at?->toDateTimeString() ?? 'null'
        );
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("[TaskJob] Task #{$this->taskId} failed permanently after all retries: {$exception->getMessage()}");

        $task = ScheduledTask::find($this->taskId);
        if ($task) {
            $task->update([
                'status' => TaskStatus::Failed,
                'last_run_at' => now(),
                'last_result' => TaskResultPrefix::Failed->format($exception->getMessage()),
                'execution_token' => null,
            ]);

            BotLog::create([
                'account_id' => $task->account_id,
                'level' => LogLevel::Error,
                'message' => "[Task][Task#{$task->id}] ".__('logs.task.job_failed', ['id' => $task->id, 'type' => $task->task_type->value, 'error' => $exception->getMessage()]),
            ]);
        }
    }
}
