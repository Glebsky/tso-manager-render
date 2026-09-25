<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\TaskStatus;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\Tasks\ScheduledTaskService;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * FR-006 / FR-010 / ADR-002 / ADR-003 — deterministic `next_run_at` and missed slots (WP-06).
 */
class NextRunAtSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private TaskSchedulerEngine $engine;

    private ScheduledTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['game.scheduler.missed_grace_minutes' => 5]);

        $this->account = Account::create([
            'username' => 'nra_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'nra_user',
        ]);

        $this->engine = $this->app->make(TaskSchedulerEngine::class);
        $this->service = $this->app->make(ScheduledTaskService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTask(array $attributes): ScheduledTask
    {
        return ScheduledTask::create(array_merge([
            'account_id' => $this->account->id,
            'name' => 'next_run_at test',
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'is_active' => true,
            'status' => TaskStatus::Completed,
        ], $attributes));
    }

    // ── create / update / toggle recompute the slot ─────────────────────────

    public function test_create_sets_next_run_at_for_every_schedule_type(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        $interval = $this->service->create([
            'account_id' => $this->account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'interval', 'interval_hours' => 0, 'interval_minutes' => 10,
        ]);
        $this->assertSame('2026-09-25 12:10:00', $interval->next_run_at?->toDateTimeString());

        $daily = $this->service->create([
            'account_id' => $this->account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'daily', 'run_at_time' => '09:30',
        ]);
        $this->assertSame('2026-09-26 09:30:00', $daily->next_run_at?->toDateTimeString());

        $once = $this->service->create([
            'account_id' => $this->account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'once', 'run_at_datetime' => '2026-09-27 08:00:00',
        ]);
        $this->assertSame('2026-09-27 08:00:00', $once->next_run_at?->toDateTimeString());
    }

    public function test_update_recomputes_slot_only_when_schedule_fields_change(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 10,
            'next_run_at' => Carbon::parse('2026-09-25 12:07:00'),
        ]);

        $task = $this->service->update($task, ['payload' => ['grid' => 202]]);
        $this->assertSame('2026-09-25 12:07:00', $task->next_run_at?->toDateTimeString(), 'payload-only edit keeps the slot');

        $task = $this->service->update($task, ['interval_hours' => 1, 'interval_minutes' => 0]);
        $this->assertSame('2026-09-25 13:00:00', $task->next_run_at?->toDateTimeString(), 'schedule edit re-anchors from now (no last_run_at)');

        $task = $this->service->update($task, ['schedule_type' => 'daily', 'run_at_time' => '15:00']);
        $this->assertSame('2026-09-25 15:00:00', $task->next_run_at?->toDateTimeString());
    }

    public function test_toggle_on_moves_an_expired_slot_to_the_future_without_missed_warning(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 10,
            'is_active' => false,
            'last_run_at' => Carbon::parse('2026-09-25 08:00:00'),
            'next_run_at' => Carbon::parse('2026-09-25 08:10:00'),
        ]);

        $task = $this->service->toggle($task);

        $this->assertTrue($task->is_active);
        $this->assertSame('2026-09-25 12:10:00', $task->refresh()->next_run_at?->toDateTimeString());
        $this->assertSame(0, $this->engine->processDueTasks(Carbon::now()));
        $this->assertNull($task->refresh()->last_result, 'no "missed slot" produced by a user action');
    }

    // ── end of a run anchors the next slot (ADR-003) ─────────────────────────

    public function test_finish_sets_next_run_at_from_the_end_of_the_run_with_second_precision(): void
    {
        Carbon::setTestNow('2026-09-25 12:03:17');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 5,
            'status' => TaskStatus::Running, 'execution_token' => 'tok',
            'next_run_at' => Carbon::parse('2026-09-25 11:58:00'),
        ]);

        $this->app->make(TaskStateMachine::class)->finish($task, 'tok', TaskStatus::Completed, 'ok', []);

        $this->assertSame('2026-09-25 12:08:17', $task->refresh()->next_run_at?->toDateTimeString());
    }

    public function test_reserving_a_once_task_clears_its_slot(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'once', 'status' => TaskStatus::Pending,
            'run_at_datetime' => Carbon::parse('2026-09-25 11:59:30'),
            'next_run_at' => Carbon::parse('2026-09-25 11:59:30'),
        ]);

        $this->assertSame(1, $this->engine->processDueTasks(Carbon::now()));

        $task->refresh();
        $this->assertSame(TaskStatus::Queued, $task->status);
        $this->assertNull($task->next_run_at);
    }

    // ── tick: grace window and missed slots (ADR-002) ────────────────────────

    public function test_slot_inside_grace_window_is_reserved(): void
    {
        Carbon::setTestNow('2026-09-25 12:04:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 30,
            'next_run_at' => Carbon::parse('2026-09-25 12:00:00'),
        ]);

        $this->assertSame(1, $this->engine->processDueTasks(Carbon::now()));
        $this->assertSame(TaskStatus::Queued, $task->refresh()->status);
        Queue::assertPushed(ExecuteScheduledTaskJob::class, 1);
    }

    public function test_missed_interval_slot_is_skipped_and_moved_to_now_plus_interval(): void
    {
        Carbon::setTestNow('2026-09-25 12:10:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 30,
            'next_run_at' => Carbon::parse('2026-09-25 12:00:00'), // 10 min late > grace 5
        ]);

        $this->assertSame(0, $this->engine->processDueTasks(Carbon::now()));

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status, 'status untouched, only the slot moved');
        $this->assertTrue($task->is_active);
        $this->assertSame('2026-09-25 12:40:00', $task->next_run_at?->toDateTimeString());
        $this->assertStringStartsWith('SKIPPED: missed slot 2026-09-25 12:00:00', (string) $task->last_result);
        Queue::assertNothingPushed();

        $this->assertSame(1, BotLog::where('account_id', $this->account->id)->where('level', LogLevel::Warning->value)->count());
    }

    public function test_daily_task_after_three_days_of_downtime_runs_zero_times_and_waits_for_the_next_slot(): void
    {
        // Slot 14:00 on the 22nd was the last one the scheduler knew about; it comes back on the 25th at 09:00.
        Carbon::setTestNow('2026-09-25 09:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'daily', 'run_at_time' => '14:00',
            'last_run_at' => Carbon::parse('2026-09-21 14:00:03'),
            'next_run_at' => Carbon::parse('2026-09-22 14:00:00'),
        ]);

        $this->assertSame(0, $this->engine->processDueTasks(Carbon::now()));
        $this->assertSame('2026-09-25 14:00:00', $task->refresh()->next_run_at?->toDateTimeString());

        // Ticks until the new slot: nothing runs.
        $this->assertSame(0, $this->engine->processDueTasks(Carbon::parse('2026-09-25 13:59:59')));
        Queue::assertNothingPushed();

        // The new slot runs normally.
        $this->assertSame(1, $this->engine->processDueTasks(Carbon::parse('2026-09-25 14:00:30')));
        Queue::assertPushed(ExecuteScheduledTaskJob::class, 1);
    }

    public function test_missed_once_task_is_failed_and_deactivated_without_running(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'once', 'status' => TaskStatus::Pending,
            'run_at_datetime' => Carbon::parse('2026-09-25 11:00:00'),
            'next_run_at' => Carbon::parse('2026-09-25 11:00:00'),
        ]);

        $this->assertSame(0, $this->engine->processDueTasks(Carbon::now()));

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertFalse($task->is_active);
        $this->assertNull($task->next_run_at);
        $this->assertStringStartsWith('SKIPPED: missed slot', (string) $task->last_result);
        Queue::assertNothingPushed();
    }

    public function test_skip_missed_slot_is_cas_so_two_ticks_do_not_move_the_slot_twice(): void
    {
        Carbon::setTestNow('2026-09-25 12:10:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 30,
            'next_run_at' => Carbon::parse('2026-09-25 12:00:00'),
        ]);
        $missed = Carbon::parse('2026-09-25 12:00:00');
        $stateMachine = $this->app->make(TaskStateMachine::class);

        $this->assertTrue($stateMachine->skipMissedSlot($task, $missed, Carbon::parse('2026-09-25 12:40:00')));
        $this->assertFalse($stateMachine->skipMissedSlot($task, $missed, Carbon::parse('2026-09-25 12:41:00')));

        $this->assertSame('2026-09-25 12:40:00', $task->refresh()->next_run_at?->toDateTimeString());
    }

    public function test_grace_defaults_to_five_minutes_when_not_configured(): void
    {
        config(['game.scheduler.missed_grace_minutes' => null]);
        Carbon::setTestNow('2026-09-25 12:05:00');
        $onTime = $this->makeTask(['schedule_type' => 'interval', 'interval_minutes' => 30, 'next_run_at' => Carbon::parse('2026-09-25 12:00:00')]);
        $late = $this->makeTask(['schedule_type' => 'interval', 'interval_minutes' => 30, 'next_run_at' => Carbon::parse('2026-09-25 11:59:59')]);

        $this->assertSame(1, $this->engine->processDueTasks(Carbon::now()));
        $this->assertSame(TaskStatus::Queued, $onTime->refresh()->status);
        $this->assertSame(TaskStatus::Completed, $late->refresh()->status);
        $this->assertStringStartsWith('SKIPPED: missed slot', (string) $late->last_result);
    }

    // ── self-healing and query shape (NFR-001) ───────────────────────────────

    public function test_rows_without_next_run_at_are_healed_from_legacy_fields(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $task = $this->makeTask([
            'schedule_type' => 'interval', 'interval_minutes' => 10,
            'last_run_at' => Carbon::parse('2026-09-25 11:58:00'),
            'next_run_at' => null,
        ]);

        // slot 12:08 is in the future -> persisted, not reserved
        $this->assertSame(0, $this->engine->processDueTasks(Carbon::now()));
        $this->assertSame('2026-09-25 12:08:00', $task->refresh()->next_run_at?->toDateTimeString());
        $this->assertSame(TaskStatus::Completed, $task->status);

        $this->assertSame(1, $this->engine->processDueTasks(Carbon::parse('2026-09-25 12:08:00')));
        $this->assertSame(TaskStatus::Queued, $task->refresh()->status);
    }

    public function test_due_selection_is_a_single_indexed_query_without_payload(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $this->makeTask(['schedule_type' => 'interval', 'interval_minutes' => 30, 'next_run_at' => Carbon::parse('2026-09-25 13:00:00')]);

        DB::enableQueryLog();
        $this->engine->processDueTasks(Carbon::now());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $dueQueries = $queries->filter(fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, 'scheduled_tasks'));

        $this->assertCount(1, $dueQueries, 'not-due tasks cost exactly one query');
        $sql = (string) $dueQueries->first();
        $this->assertStringNotContainsString('payload', $sql);
        $this->assertStringContainsString('"is_active" = ?', $sql);
        $this->assertStringContainsString('"next_run_at" <= ?', $sql);
    }
}
