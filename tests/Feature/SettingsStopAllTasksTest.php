<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsStopAllTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_all_tasks_deactivates_and_cancels_busy_tasks(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'en',
            'nickname' => 'tester',
            'status' => 'online',
        ]);

        $idleTask = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Idle Task',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 30,
            'is_active' => true,
            'status' => TaskStatus::Pending,
            'payload' => ['actions' => []],
        ]);

        $runningTask = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Running Task',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 30,
            'is_active' => true,
            'status' => TaskStatus::Running,
            'execution_token' => 'token-run',
            'payload' => ['actions' => []],
        ]);

        $queuedTask = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Queued Task',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 30,
            'is_active' => true,
            'status' => TaskStatus::Queued,
            'execution_token' => 'token-queue',
            'payload' => ['actions' => []],
        ]);

        $response = $this->actingAs($user)->postJson('/api/settings/tasks/stop');

        $response->assertNoContent();

        $idleTask->refresh();
        $runningTask->refresh();
        $queuedTask->refresh();

        $this->assertFalse($idleTask->is_active);
        $this->assertSame(TaskStatus::Pending, $idleTask->status);

        $this->assertFalse($runningTask->is_active);
        $this->assertSame(TaskStatus::Cancelled, $runningTask->status);
        $this->assertNull($runningTask->execution_token);

        $this->assertFalse($queuedTask->is_active);
        $this->assertSame(TaskStatus::Cancelled, $queuedTask->status);
        $this->assertNull($queuedTask->execution_token);
    }
}
