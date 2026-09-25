<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskStatus;
use App\Models\ScheduledTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Waits between sequence steps while keeping the run alive (FR-007).
 *
 * The wait is chunked into one-second sleeps so that a user "Stop" is noticed
 * quickly, and `heartbeat_at` is refreshed at least every
 * `game.scheduler.heartbeat_interval_seconds` so stale recovery never mistakes
 * a healthy pause for a hung run. Uses `Illuminate\Support\Sleep` so tests can
 * `Sleep::fake()`.
 */
final readonly class DelayWaiter
{
    public function __construct(private TaskStateMachine $stateMachine) {}

    /**
     * Wait `$seconds` on behalf of the run identified by `$token`.
     *
     * Returns false as soon as the token no longer owns the task (cancelled,
     * finished by someone else, recovered as stale) — the caller must stop.
     */
    public function wait(ScheduledTask $task, string $token, int $seconds): bool
    {
        if ($seconds <= 0) {
            return true;
        }

        $interval = max(1, (int) config('game.scheduler.heartbeat_interval_seconds', 15));
        $sinceHeartbeat = 0;

        for ($remaining = $seconds; $remaining > 0; $remaining--) {
            Sleep::for(1)->second();
            $sinceHeartbeat++;

            $task->refresh();

            if (! $this->ownsRun($task, $token)) {
                Log::info("[DelayWaiter] Task #{$task->id} lost ownership during delay wait (status={$task->status->value}); aborting wait.");

                return false;
            }

            if ($sinceHeartbeat >= $interval && $remaining > 1) {
                if (! $this->stateMachine->heartbeat($task, $token)) {
                    return false;
                }
                $sinceHeartbeat = 0;
            }
        }

        return true;
    }

    private function ownsRun(ScheduledTask $task, string $token): bool
    {
        return $task->execution_token === $token
            && in_array($task->status, [TaskStatus::Queued, TaskStatus::Running], true);
    }
}
