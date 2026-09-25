<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TaskGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_group_is_persisted_exposed_and_editable(): void
    {
        $account = Account::create([
            'username' => 'group-user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'Group User',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $payload = [
            'actions' => [[
                'task_type' => 'stop_production',
                'payload' => ['grid' => 1234],
                'delay_seconds' => 0,
            ]],
        ];

        $response = $this->postJson('/api/tasks', [
            'name' => 'Morning production',
            'group_name' => 'Daily routine',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => $payload,
            'schedule_type' => 'daily',
            'run_at_time' => '08:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('task.group_name', 'Daily routine');

        $task = ScheduledTask::query()->latest('id')->firstOrFail();
        $this->assertSame('Daily routine', $task->group_name);

        $this->putJson("/api/tasks/{$task->id}", [
            'name' => 'Morning production',
            'group_name' => 'Evening routine',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => $payload,
            'schedule_type' => 'daily',
            'run_at_time' => '08:00',
        ])->assertOk()
            ->assertJsonPath('task.group_name', 'Evening routine');

        $this->assertDatabaseHas('scheduled_tasks', [
            'id' => $task->id,
            'group_name' => 'Evening routine',
        ]);
    }

    public function test_duplicate_keeps_task_group(): void
    {
        $account = Account::create([
            'username' => 'duplicate-group-user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'Duplicate Group User',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task = ScheduledTask::create([
            'name' => 'Grouped task',
            'group_name' => 'Daily routine',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => ['actions' => []],
            'schedule_type' => 'daily',
            'run_at_time' => '08:00',
        ]);

        $this->postJson("/api/tasks/{$task->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('task.group_name', 'Daily routine');
    }
}
