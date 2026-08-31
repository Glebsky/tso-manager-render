<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskSchedulerEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_task_due_evaluates_once_daily_and_interval_types(): void
    {
        $engine = $this->app->make(TaskSchedulerEngine::class);
        $now = Carbon::parse('2026-08-02 12:00:00');

        $dailyTask = new ScheduledTask([
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
        ]);
        $this->assertTrue($engine->isTaskDue($dailyTask, $now));

        $onceTaskDue = new ScheduledTask([
            'schedule_type' => 'once',
            'run_at_datetime' => '2026-08-02 11:59:00',
        ]);
        $this->assertTrue($engine->isTaskDue($onceTaskDue, $now));

        $intervalTaskDue = new ScheduledTask([
            'schedule_type' => 'interval',
            'interval_hours' => 1,
            'interval_minutes' => 0,
            'last_run_at' => Carbon::parse('2026-08-02 10:50:00'),
        ]);
        $this->assertTrue($engine->isTaskDue($intervalTaskDue, $now));
    }

    public function test_recover_stale_tasks_resets_stuck_tasks(): void
    {
        $account = Account::create([
            'username' => 'staletest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'staletest',
        ]);

        $staleTask = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 100],
            'schedule_type' => 'once',
            'status' => 'running',
            'queued_at' => now()->subMinutes(20),
            'execution_token' => 'old_token',
        ]);

        $engine = $this->app->make(TaskSchedulerEngine::class);
        $recoveredCount = $engine->recoverStaleTasks(10);

        $this->assertEquals(1, $recoveredCount);
        $staleTask->refresh();
        $this->assertEquals('pending', $staleTask->status->value);
        $this->assertNull($staleTask->execution_token);
    }

    public function test_reserve_and_dispatch_task_locks_task_atomically(): void
    {
        Queue::fake();

        $account = Account::create([
            'username' => 'reservetest',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'reservetest',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 105],
            'schedule_type' => 'once',
            'status' => 'pending',
            'is_active' => true,
        ]);

        $engine = $this->app->make(TaskSchedulerEngine::class);
        $reserved = $engine->reserveAndDispatchTask($task, 'queue');

        $this->assertTrue($reserved);
        $task->refresh();
        $this->assertEquals('queued', $task->status->value);
        $this->assertNotNull($task->execution_token);
    }
}
