<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ScheduleType;
use App\Models\ScheduledTask;
use App\Services\Tasks\Scheduling\ScheduleCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-006 / FR-010 / ADR-002 / ADR-003 — `next_run_at` arithmetic (V-06, V-13, V-14).
 * All instants are UTC.
 */
class ScheduleCalculatorTest extends TestCase
{
    private ScheduleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ScheduleCalculator;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(array $attributes): ScheduledTask
    {
        return new ScheduledTask($attributes);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC');
    }

    /** Model datetime attributes are mutable Carbon instances. */
    private function stored(string $time): Carbon
    {
        return Carbon::parse($time, 'UTC');
    }

    // ── nextRunAt (after a run ended) ────────────────────────────────────────

    public function test_interval_counts_from_the_end_of_the_run_with_second_precision(): void
    {
        // V-14: interval 5 min, run finished 12:03:17 -> 12:08:17 (no rounding to minutes).
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_hours' => 0, 'interval_minutes' => 5]);

        $next = $this->calculator->nextRunAt($task, $this->at('2026-09-25 12:03:17'));

        $this->assertSame('2026-09-25 12:08:17', $next?->toDateTimeString());
    }

    public function test_interval_hours_and_minutes_are_combined(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_hours' => 1, 'interval_minutes' => 30]);

        $next = $this->calculator->nextRunAt($task, $this->at('2026-09-25 23:00:00'));

        $this->assertSame('2026-09-26 00:30:00', $next?->toDateTimeString());
    }

    public function test_zero_interval_has_no_next_slot(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_hours' => 0, 'interval_minutes' => 0]);

        $this->assertNull($this->calculator->nextRunAt($task, $this->at('2026-09-25 12:00:00')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dailyAfterEndProvider(): array
    {
        return [
            'run ended before today\'s slot -> today' => ['2026-09-25 09:15:00', '2026-09-25 14:00:00'],
            'run ended exactly at the slot -> tomorrow (strictly after)' => ['2026-09-25 14:00:00', '2026-09-26 14:00:00'],
            'run ended after the slot -> tomorrow' => ['2026-09-25 14:00:42', '2026-09-26 14:00:00'],
            'run ended at end of month -> next month' => ['2026-09-30 15:00:00', '2026-10-01 14:00:00'],
        ];
    }

    #[DataProvider('dailyAfterEndProvider')]
    public function test_daily_next_slot_is_strictly_after_the_end_of_the_run(string $endedAt, string $expected): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => '14:00']);

        $next = $this->calculator->nextRunAt($task, $this->at($endedAt));

        $this->assertSame($expected, $next?->toDateTimeString());
    }

    public function test_daily_without_run_at_time_has_no_slot(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => null]);

        $this->assertNull($this->calculator->nextRunAt($task, $this->at('2026-09-25 12:00:00')));
    }

    public function test_once_has_no_next_slot_after_it_ran(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Once, 'run_at_datetime' => '2026-09-25 12:00:00']);

        $this->assertNull($this->calculator->nextRunAt($task, $this->at('2026-09-25 12:00:00')));
    }

    // ── initialRunAt (derived from persisted fields) ─────────────────────────

    public function test_initial_once_is_run_at_datetime_until_it_ran(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Once, 'run_at_datetime' => '2026-09-26 08:00:00']);
        $this->assertSame('2026-09-26 08:00:00', $this->calculator->initialRunAt($task, $this->at('2026-09-25 12:00:00'))?->toDateTimeString());

        $task->last_run_at = $this->stored('2026-09-26 08:00:05');
        $this->assertNull($this->calculator->initialRunAt($task, $this->at('2026-09-26 09:00:00')));
    }

    public function test_initial_interval_is_last_run_plus_interval_or_created_plus_interval(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_minutes' => 10]);
        $task->created_at = $this->stored('2026-09-25 11:00:00');

        $this->assertSame('2026-09-25 11:10:00', $this->calculator->initialRunAt($task, $this->at('2026-09-25 11:03:00'))?->toDateTimeString());

        $task->last_run_at = $this->stored('2026-09-25 11:42:13');
        $this->assertSame('2026-09-25 11:52:13', $this->calculator->initialRunAt($task, $this->at('2026-09-25 11:45:00'))?->toDateTimeString());
    }

    public function test_initial_daily_never_ran_takes_todays_slot_if_not_yet_passed(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => '12:00']);

        // created at 12:00:00 sharp -> today (at/after now)
        $this->assertSame('2026-09-25 12:00:00', $this->calculator->initialRunAt($task, $this->at('2026-09-25 12:00:00'))?->toDateTimeString());
        // created at 12:00:01 -> tomorrow
        $this->assertSame('2026-09-26 12:00:00', $this->calculator->initialRunAt($task, $this->at('2026-09-25 12:00:01'))?->toDateTimeString());
    }

    public function test_initial_daily_after_last_run_is_the_next_slot_after_that_run(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => '14:00']);
        $task->last_run_at = $this->stored('2026-09-24 14:00:10');

        $this->assertSame('2026-09-25 14:00:00', $this->calculator->initialRunAt($task, $this->at('2026-09-25 14:15:00'))?->toDateTimeString());
    }

    // ── isMissed (grace window, ADR-002) ─────────────────────────────────────

    /**
     * @return array<string, array{string, bool}>
     */
    public static function graceBoundaryProvider(): array
    {
        return [
            'on time' => ['2026-09-25 12:00:00', false],
            '1 s late' => ['2026-09-25 12:00:01', false],
            '4:59 late' => ['2026-09-25 12:04:59', false],
            'exactly grace (5:00) late -> still on time' => ['2026-09-25 12:05:00', false],
            'grace + 1 s -> missed' => ['2026-09-25 12:05:01', true],
            '3 days late -> missed' => ['2026-09-28 12:00:00', true],
        ];
    }

    #[DataProvider('graceBoundaryProvider')]
    public function test_is_missed_boundary(string $now, bool $expected): void
    {
        $slot = $this->at('2026-09-25 12:00:00');

        $this->assertSame($expected, $this->calculator->isMissed($slot, $this->at($now), 5));
    }

    // ── nextFutureSlot (where a missed slot moves to) ────────────────────────

    public function test_missed_interval_moves_to_now_plus_interval(): void
    {
        // A-1
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_minutes' => 30]);

        $this->assertSame('2026-09-25 12:40:00', $this->calculator->nextFutureSlot($task, $this->at('2026-09-25 12:10:00'))?->toDateTimeString());
    }

    public function test_missed_daily_after_three_days_down_moves_to_the_next_future_slot_only(): void
    {
        // Scheduler was down 3 days: exactly one future slot, no catch-up.
        $task = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => '14:00']);

        $this->assertSame('2026-09-28 14:00:00', $this->calculator->nextFutureSlot($task, $this->at('2026-09-28 09:00:00'))?->toDateTimeString());
        $this->assertSame('2026-09-29 14:00:00', $this->calculator->nextFutureSlot($task, $this->at('2026-09-28 14:00:00'))?->toDateTimeString());
    }

    public function test_missed_once_has_no_future_slot(): void
    {
        // A-2: the tick fails it instead.
        $task = $this->task(['schedule_type' => ScheduleType::Once, 'run_at_datetime' => '2026-09-25 12:00:00']);

        $this->assertNull($this->calculator->nextFutureSlot($task, $this->at('2026-09-28 09:00:00')));
    }

    // ── scheduleRunAt (operator create/update/toggle) ────────────────────────

    public function test_schedule_run_at_keeps_a_slot_inside_the_grace_window(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_minutes' => 10]);
        $task->last_run_at = $this->stored('2026-09-25 11:52:00');

        // slot 12:02, now 12:04 -> 2 min late, inside grace -> keep
        $this->assertSame('2026-09-25 12:02:00', $this->calculator->scheduleRunAt($task, $this->at('2026-09-25 12:04:00'), 5)?->toDateTimeString());
    }

    public function test_schedule_run_at_replaces_a_missed_slot_with_the_next_future_one(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Interval, 'interval_minutes' => 10]);
        $task->last_run_at = $this->stored('2026-09-25 09:00:00');

        // Paused for hours, toggled on at 12:00 -> now + interval, no "missed" warning path
        $this->assertSame('2026-09-25 12:10:00', $this->calculator->scheduleRunAt($task, $this->at('2026-09-25 12:00:00'), 5)?->toDateTimeString());

        $daily = $this->task(['schedule_type' => ScheduleType::Daily, 'run_at_time' => '14:00']);
        $daily->last_run_at = $this->stored('2026-09-20 14:00:00');
        $this->assertSame('2026-09-25 14:00:00', $this->calculator->scheduleRunAt($daily, $this->at('2026-09-25 10:00:00'), 5)?->toDateTimeString());
        $this->assertSame('2026-09-26 14:00:00', $this->calculator->scheduleRunAt($daily, $this->at('2026-09-25 15:00:00'), 5)?->toDateTimeString());
    }

    public function test_schedule_run_at_returns_a_past_once_slot_unchanged(): void
    {
        $task = $this->task(['schedule_type' => ScheduleType::Once, 'run_at_datetime' => '2026-09-25 08:00:00']);

        $this->assertSame('2026-09-25 08:00:00', $this->calculator->scheduleRunAt($task, $this->at('2026-09-25 12:00:00'), 5)?->toDateTimeString());
    }
}
