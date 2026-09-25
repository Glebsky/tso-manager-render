<?php

declare(strict_types=1);

namespace App\Services\Tasks\Scheduling;

use App\Enums\ScheduleType;
use App\Models\ScheduledTask;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pure schedule arithmetic for `next_run_at` (FR-006, FR-010, ADR-002/003).
 *
 * No I/O, no clock: every method receives the reference instant explicitly.
 * All instants are handled in the application timezone (UTC by default);
 * `run_at_time` is interpreted in that same timezone.
 */
final class ScheduleCalculator
{
    /**
     * Next slot strictly after `$from` — used when a run has just ended
     * (`completed`/`failed`/`cancelled`/stale-recovered). The interval counts
     * from the end of the run, with second precision (ADR-003).
     *
     * `once` has no next slot.
     */
    public function nextRunAt(ScheduledTask $task, CarbonInterface $from): ?CarbonImmutable
    {
        $from = CarbonImmutable::instance($from);

        return match ($task->schedule_type) {
            ScheduleType::Once => null,
            ScheduleType::Interval => $this->addInterval($task, $from),
            ScheduleType::Daily => $this->dailySlotAfter($task, $from, strict: true),
        };
    }

    /**
     * The slot the task is currently waiting for, derived from its persisted
     * fields — used for backfill and for rows created without `next_run_at`.
     *
     * - once: `run_at_datetime` until it has run;
     * - interval: `(last_run_at ?? created_at ?? now) + interval`;
     * - daily: next `run_at_time` strictly after `last_run_at`, or the first
     *   `run_at_time` at/after `now` if it never ran.
     */
    public function initialRunAt(ScheduledTask $task, CarbonInterface $now): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now);
        $lastRunAt = $task->last_run_at !== null ? CarbonImmutable::instance($task->last_run_at) : null;

        return match ($task->schedule_type) {
            ScheduleType::Once => $lastRunAt === null && $task->run_at_datetime !== null
                ? CarbonImmutable::instance($task->run_at_datetime)
                : null,
            ScheduleType::Interval => $this->addInterval(
                $task,
                $lastRunAt ?? ($task->created_at !== null ? CarbonImmutable::instance($task->created_at) : $now)
            ),
            ScheduleType::Daily => $lastRunAt !== null
                ? $this->dailySlotAfter($task, $lastRunAt, strict: true)
                : $this->dailySlotAfter($task, $now, strict: false),
        };
    }

    /**
     * Slot to persist when the operator (re)defines the schedule — create,
     * update, toggle on. Same as {@see initialRunAt}, except that a slot that
     * is already missed (older than the grace window) is replaced by the next
     * future slot, so a user action never produces a "missed slot" warning.
     *
     * A `once` slot is returned as is: the tick decides whether it is missed.
     */
    public function scheduleRunAt(ScheduledTask $task, CarbonInterface $now, int $graceMinutes): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now);
        $slot = $this->initialRunAt($task, $now);

        if ($slot === null || $task->schedule_type === ScheduleType::Once) {
            return $slot;
        }

        return $this->isMissed($slot, $now, $graceMinutes) ? $this->nextFutureSlot($task, $now) : $slot;
    }

    /**
     * A slot is missed when the tick arrives more than `$graceMinutes` after
     * it (ADR-002). Exactly at the boundary the slot is still on time.
     */
    public function isMissed(CarbonInterface $nextRunAt, CarbonInterface $now, int $graceMinutes): bool
    {
        return CarbonImmutable::instance($nextRunAt)->addMinutes(max(0, $graceMinutes))->lessThan($now);
    }

    /**
     * Where a missed slot is moved to (ADR-002):
     * interval → `now + interval` (A-1); daily → next `run_at_time` strictly
     * after now; once → null (the task is failed instead, A-2).
     */
    public function nextFutureSlot(ScheduledTask $task, CarbonInterface $now): ?CarbonImmutable
    {
        return $this->nextRunAt($task, $now);
    }

    public function intervalSeconds(ScheduledTask $task): int
    {
        $hours = max(0, (int) $task->interval_hours);
        $minutes = max(0, (int) $task->interval_minutes);

        return ($hours * 3600) + ($minutes * 60);
    }

    private function addInterval(ScheduledTask $task, CarbonImmutable $from): ?CarbonImmutable
    {
        $seconds = $this->intervalSeconds($task);

        return $seconds > 0 ? $from->addSeconds($seconds) : null;
    }

    /**
     * First occurrence of `run_at_time` after `$from` (strictly, or at/after
     * when `$strict` is false).
     */
    private function dailySlotAfter(ScheduledTask $task, CarbonImmutable $from, bool $strict): ?CarbonImmutable
    {
        $time = (string) $task->run_at_time;
        if ($time === '') {
            return null;
        }

        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);

        $candidate = $from->setTime($hours, $minutes, $seconds);

        $isPast = $strict ? $candidate->lessThanOrEqualTo($from) : $candidate->lessThan($from);

        return $isPast ? $candidate->addDay() : $candidate;
    }
}
