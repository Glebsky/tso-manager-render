<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\ScheduledTaskService;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * FR-003 / ADR-001 / ADR-003: "Stop" interrupts only the current run.
 * A recurring task stays on its schedule (cancelled is not terminal) and the
 * next slot is counted from the moment it was stopped; a once task is not
 * re-run automatically.
 */
class CancelledTaskReschedulingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->account = Account::create([
            'username' => 'cancelled_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'cancelled_user',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTask(array $attributes): ScheduledTask
    {
        return ScheduledTask::create(array_merge([
            'account_id' => $this->account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'is_active' => true,
            'status' => TaskStatus::Cancelled,
            'execution_token' => null,
        ], $attributes));
    }

    public function test_cancelled_interval_task_is_reserved_when_its_slot_is_due(): void
    {
        $task = $this->makeTask([
            'schedule_type' => 'interval',
            'interval_hours' => 0,
            'interval_minutes' => 10,
            'last_run_at' => now()->subMinutes(11),
        ]);

        $count = $this->app->make(TaskSchedulerEngine::class)->processDueTasks(Carbon::now());

        $this->assertSame(1, $count);
        $task->refresh();
        $this->assertSame(TaskStatus::Queued, $task->status);
        $this->assertNotNull($task->execution_token);
        Queue::assertPushed(ExecuteScheduledTaskJob::class, 1);
    }

    public function test_cancelled_daily_task_is_reserved_when_its_slot_is_due(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:30');

        $task = $this->makeTask([
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'last_run_at' => Carbon::parse('2026-09-24 12:00:10'),
        ]);

        $count = $this->app->make(TaskSchedulerEngine::class)->processDueTasks(Carbon::now());

        $this->assertSame(1, $count);
        $this->assertSame(TaskStatus::Queued, $task->refresh()->status);
    }

    public function test_cancelled_task_is_not_reserved_when_inactive(): void
    {
        $task = $this->makeTask([
            'schedule_type' => 'interval',
            'interval_minutes' => 10,
            'last_run_at' => now()->subMinutes(11),
            'is_active' => false,
        ]);

        $count = $this->app->make(TaskSchedulerEngine::class)->processDueTasks(Carbon::now());

        $this->assertSame(0, $count);
        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_stopping_a_running_interval_task_starts_the_next_interval_from_the_stop_moment(): void
    {
        $stoppedAt = Carbon::parse('2026-09-25 12:03:17');
        Carbon::setTestNow($stoppedAt);

        $task = $this->makeTask([
            'schedule_type' => 'interval',
            'interval_hours' => 0,
            'interval_minutes' => 5,
            'status' => TaskStatus::Running,
            'execution_token' => 'run-token',
            'last_run_at' => $stoppedAt->copy()->subMinutes(30),
        ]);

        $this->app->make(ScheduledTaskService::class)->stop($task);

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertTrue($task->is_active, 'stop must not unschedule a recurring task');
        $this->assertNotNull($task->last_run_at);
        $this->assertTrue($stoppedAt->equalTo($task->last_run_at), 'stop is the end of the run (ADR-003)');

        $engine = $this->app->make(TaskSchedulerEngine::class);

        $this->assertSame(0, $engine->processDueTasks($stoppedAt->copy()->addMinutes(4)), 'must not run before T + interval');
        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);

        $this->assertSame(1, $engine->processDueTasks($stoppedAt->copy()->addMinutes(5)), 'must run at T + interval');
        $this->assertSame(TaskStatus::Queued, $task->refresh()->status);
    }

    public function test_stopping_a_running_once_task_deactivates_it(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        $task = $this->makeTask([
            'schedule_type' => 'once',
            'run_at_datetime' => Carbon::parse('2026-09-25 11:59:00'),
            'status' => TaskStatus::Running,
            'execution_token' => 'run-token',
            'last_run_at' => null,
        ]);

        $this->app->make(ScheduledTaskService::class)->stop($task);

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertFalse($task->is_active, 'a stopped once task has no next slot (ADR-001)');

        $count = $this->app->make(TaskSchedulerEngine::class)->processDueTasks(Carbon::now()->addMinutes(10));

        $this->assertSame(0, $count);
        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_stopping_an_idle_task_does_not_shift_its_schedule(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        $lastRunAt = Carbon::parse('2026-09-25 11:50:00');

        $task = $this->makeTask([
            'schedule_type' => 'interval',
            'interval_minutes' => 15,
            'status' => TaskStatus::Completed,
            'execution_token' => null,
            'last_run_at' => $lastRunAt,
        ]);

        $this->app->make(ScheduledTaskService::class)->stop($task);

        $task->refresh();
        $this->assertNotNull($task->last_run_at);
        $this->assertTrue($lastRunAt->equalTo($task->last_run_at), 'nothing was running, so nothing finished');
        $this->assertTrue($task->is_active);
    }
}
