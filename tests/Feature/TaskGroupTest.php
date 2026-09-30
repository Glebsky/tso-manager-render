<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::create([
            'username' => 'test_user',
            'server' => 'ru',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);
    }

    public function test_can_list_task_groups(): void
    {
        TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Group A', 'sort_order' => 1]);
        TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Group B', 'sort_order' => 2]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/task-groups?account_id={$this->account->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Group A')
            ->assertJsonPath('data.1.name', 'Group B');
    }

    public function test_can_create_task_group(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/task-groups', [
                'account_id' => $this->account->id,
                'name' => 'Daily Buffs',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('group.name', 'Daily Buffs')
            ->assertJsonPath('group.sort_order', 1);

        $this->assertDatabaseHas('task_groups', [
            'account_id' => $this->account->id,
            'name' => 'Daily Buffs',
            'sort_order' => 1,
        ]);
    }

    public function test_cannot_create_duplicate_task_group_for_same_account(): void
    {
        TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Daily Buffs', 'sort_order' => 1]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/task-groups', [
                'account_id' => $this->account->id,
                'name' => 'Daily Buffs',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_rename_task_group_and_cascades_to_tasks(): void
    {
        $group = TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Old Name', 'sort_order' => 1]);

        $task = ScheduledTask::create([
            'account_id' => $this->account->id,
            'name' => 'Task 1',
            'group_name' => 'Old Name',
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/task-groups/{$group->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('group.name', 'New Name');

        $this->assertDatabaseHas('task_groups', [
            'id' => $group->id,
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $task->fresh()?->group_name);
    }

    public function test_can_delete_task_group_and_unlinks_tasks(): void
    {
        $group = TaskGroup::create(['account_id' => $this->account->id, 'name' => 'To Delete', 'sort_order' => 1]);

        $task = ScheduledTask::create([
            'account_id' => $this->account->id,
            'name' => 'Task 1',
            'group_name' => 'To Delete',
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/task-groups/{$group->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('task_groups', ['id' => $group->id]);
        $this->assertNull($task->fresh()?->group_name);
    }

    public function test_can_reorder_task_groups(): void
    {
        $g1 = TaskGroup::create(['account_id' => $this->account->id, 'name' => 'First', 'sort_order' => 1]);
        $g2 = TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Second', 'sort_order' => 2]);
        $g3 = TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Third', 'sort_order' => 3]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/task-groups/reorder', [
                'group_ids' => [$g3->id, $g1->id, $g2->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(1, $g3->fresh()?->sort_order);
        $this->assertEquals(2, $g1->fresh()?->sort_order);
        $this->assertEquals(3, $g2->fresh()?->sort_order);
    }

    public function test_tasks_index_includes_task_groups(): void
    {
        TaskGroup::create(['account_id' => $this->account->id, 'name' => 'Production', 'sort_order' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'accounts',
                'groups',
                'meta',
            ])
            ->assertJsonPath('groups.0.name', 'Production');
    }

    public function test_can_update_task_group_name_directly(): void
    {
        $task = ScheduledTask::create([
            'account_id' => $this->account->id,
            'name' => 'Task 1',
            'group_name' => 'Old Group',
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", [
                'group_name' => 'New Group',
            ]);

        $response->assertOk();
        $this->assertEquals('New Group', $task->fresh()?->group_name);

        // Can also clear group_name (move to ungrouped)
        $response = $this->actingAs($this->user)
            ->putJson("/api/tasks/{$task->id}", [
                'group_name' => null,
            ]);

        $response->assertOk();
        $this->assertNull($task->fresh()?->group_name);
    }
}
