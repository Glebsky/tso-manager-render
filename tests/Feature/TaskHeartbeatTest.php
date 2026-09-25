<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\DelayWaiter;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * FR-007 — heartbeat and stale recovery (WP-05).
 */
class TaskHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private TaskStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = $this->app->make(TaskStateMachine::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(array $attributes = []): ScheduledTask
    {
        $account = Account::create([
            'username' => 'hb_user_'.uniqid(),
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'hb_user',
        ]);

        return ScheduledTask::create(array_merge([
            'account_id' => $account->id,
            'name' => 'Heartbeat Test Task',
            'task_type' => 'stop_production',
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 30,
            'is_active' => true,
            'status' => TaskStatus::Running,
            'execution_token' => 'hb-token',
            'payload' => ['grid' => 100],
        ], $attributes));
    }

    public function test_reserve_and_start_set_heartbeat(): void
    {
        $task = $this->createTask(['status' => TaskStatus::Pending, 'execution_token' => null]);

        $this->assertTrue($this->stateMachine->reserve($task, 'tok'));
        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);

        $task->forceFill(['heartbeat_at' => Carbon::now()->subMinutes(3)])->save();
        $this->assertTrue($this->stateMachine->start($task, 'tok'));
        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertTrue($task->heartbeat_at->gt(Carbon::now()->subMinute()));
    }

    public function test_heartbeat_is_cas_by_token(): void
    {
        $old = Carbon::now()->subMinutes(5);
        $task = $this->createTask(['heartbeat_at' => $old]);

        $this->assertFalse($this->stateMachine->heartbeat($task, 'wrong-token'));
        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertSame($old->timestamp, $task->heartbeat_at->timestamp);

        $this->assertTrue($this->stateMachine->heartbeat($task, 'hb-token'));
        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertTrue($task->heartbeat_at->gt($old));
    }

    public function test_heartbeat_can_be_pushed_into_the_future_for_hand_off(): void
    {
        $task = $this->createTask();
        $until = Carbon::now()->addSeconds(120);

        $this->assertTrue($this->stateMachine->heartbeat($task, 'hb-token', $until));

        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertSame($until->timestamp, $task->heartbeat_at->timestamp);
    }

    public function test_stale_recovery_uses_config_timeout_and_heartbeat(): void
    {
        config(['game.stale_task_timeout_minutes' => 5]);

        // Reserved long ago but still heartbeating -> healthy.
        $healthy = $this->createTask([
            'queued_at' => Carbon::now()->subMinutes(30),
            'heartbeat_at' => Carbon::now()->subMinutes(2),
        ]);

        // Heartbeat older than the configured timeout -> stale.
        $stale = $this->createTask([
            'queued_at' => Carbon::now()->subMinutes(30),
            'heartbeat_at' => Carbon::now()->subMinutes(6),
        ]);

        // Heartbeat older than the default (10) but within configured 5? No:
        // 4 min is inside the 5 min window -> healthy.
        $recent = $this->createTask([
            'heartbeat_at' => Carbon::now()->subMinutes(4),
        ]);

        $engine = $this->app->make(TaskSchedulerEngine::class);
        $recovered = $engine->recoverStaleTasks();

        $this->assertSame(1, $recovered);

        $healthy->refresh();
        $stale->refresh();
        $recent->refresh();

        $this->assertSame(TaskStatus::Running, $healthy->status);
        $this->assertSame(TaskStatus::Running, $recent->status);
        $this->assertSame(TaskStatus::Pending, $stale->status);
        $this->assertNull($stale->execution_token);
        $this->assertNull($stale->heartbeat_at);
    }

    public function test_stale_once_task_becomes_failed_and_inactive(): void
    {
        $task = $this->createTask([
            'schedule_type' => ScheduleType::Once,
            'run_at_datetime' => Carbon::now()->subHour(),
            'heartbeat_at' => Carbon::now()->subMinutes(30),
        ]);

        $engine = $this->app->make(TaskSchedulerEngine::class);
        $this->assertSame(1, $engine->recoverStaleTasks());

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertFalse($task->is_active);
        $this->assertNull($task->execution_token);
        $this->assertStringStartsWith('ERROR:', (string) $task->last_result);
    }

    public function test_stale_recurring_task_keeps_is_active_and_becomes_pending(): void
    {
        $task = $this->createTask([
            'schedule_type' => ScheduleType::Daily,
            'run_at_time' => '12:00',
            'heartbeat_at' => Carbon::now()->subMinutes(30),
        ]);

        $engine = $this->app->make(TaskSchedulerEngine::class);
        $this->assertSame(1, $engine->recoverStaleTasks());

        $task->refresh();
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertTrue($task->is_active);
        $this->assertStringStartsWith('WARNING:', (string) $task->last_result);
    }

    public function test_delay_waiter_heartbeats_at_least_every_interval(): void
    {
        Sleep::fake();
        config(['game.scheduler.heartbeat_interval_seconds' => 10]);

        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));
        $start = Carbon::now()->subMinutes(2);
        $task = $this->createTask(['heartbeat_at' => $start]);

        $heartbeats = 0;
        $lastSeen = $start;
        Sleep::whenFakingSleep(function () use ($task, &$heartbeats, &$lastSeen): void {
            // Every faked second advances the clock like a real one would.
            Carbon::setTestNow(Carbon::now()->addSecond());

            $current = ScheduledTask::query()->whereKey($task->id)->value('heartbeat_at');
            $current = Carbon::parse($current);
            if ($current->gt($lastSeen)) {
                $heartbeats++;
                $lastSeen = $current;
            }
        });

        $waiter = $this->app->make(DelayWaiter::class);
        $result = $waiter->wait($task, 'hb-token', 35);

        $this->assertTrue($result);
        Sleep::assertSleptTimes(35);
        // 35 s wait with a 10 s interval: heartbeats after 10, 20, 30 s.
        $this->assertGreaterThanOrEqual(3, $heartbeats);

        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertTrue($task->heartbeat_at->gt($start));
    }

    public function test_delay_waiter_aborts_when_task_is_stopped_mid_wait(): void
    {
        Sleep::fake();

        $task = $this->createTask();

        $tick = 0;
        Sleep::whenFakingSleep(function () use ($task, &$tick): void {
            $tick++;
            if ($tick === 2) {
                // User presses Stop while the sequence is waiting between steps.
                $this->stateMachine->cancel($task, 'stopped');
            }
        });

        $waiter = $this->app->make(DelayWaiter::class);
        $result = $waiter->wait($task, 'hb-token', 30);

        $this->assertFalse($result);
        Sleep::assertSleptTimes(2);

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
    }

    public function test_delay_waiter_returns_immediately_for_zero_delay(): void
    {
        Sleep::fake();
        $task = $this->createTask();

        $this->assertTrue($this->app->make(DelayWaiter::class)->wait($task, 'hb-token', 0));
        Sleep::assertNeverSlept();
    }
}
