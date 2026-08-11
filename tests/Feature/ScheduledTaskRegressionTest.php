<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\Tasks\TaskSchedulerEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledTaskRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $zoneData = [
            'level' => 50,
            'buildings' => [
                ['buildingGrid' => 1234, 'name' => 'Pinewood Sawmill', 'level' => 3],
                ['buildingGrid' => 5678, 'name' => 'Hunter', 'level' => 2],
            ],
            'specialists' => [],
            'buffs' => [],
        ];

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'username' => 'test_user',
            'nickname' => 'TestPlayer',
            'password' => 'secret123',
            'server' => 'ru_1',
            'zone_data' => json_encode($zoneData),
        ]);
    }

    public function test_building_name_is_preserved_and_auto_enriched_in_payload(): void
    {
        $this->actingAs($this->user);

        // 1. Create building task with explicit building_name
        $response1 = $this->postJson('/api/tasks', [
            'account_id' => $this->account->id,
            'task_type' => TaskType::StopProduction->value,
            'schedule_type' => ScheduleType::Daily->value,
            'run_at_time' => '12:00',
            'payload' => [
                'grid' => 1234,
                'building_name' => 'Pinewood Sawmill',
            ],
        ]);

        $response1->assertCreated();
        $this->assertSame('Pinewood Sawmill', $response1->json('task.payload.building_name'));

        // 2. Create building task without explicit building_name (should be auto-enriched from account zone_data)
        $response2 = $this->postJson('/api/tasks', [
            'account_id' => $this->account->id,
            'task_type' => TaskType::StartProduction->value,
            'schedule_type' => ScheduleType::Daily->value,
            'run_at_time' => '12:30',
            'payload' => [
                'grid' => 5678,
            ],
        ]);

        $response2->assertCreated();
        $this->assertSame('Hunter', $response2->json('task.payload.building_name'));

        // 3. Create sequence task with building actions (should preserve/enrich building_name in each action payload)
        $response3 = $this->postJson('/api/tasks', [
            'account_id' => $this->account->id,
            'task_type' => TaskType::Sequence->value,
            'schedule_type' => ScheduleType::Daily->value,
            'run_at_time' => '13:00',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => TaskType::StopProduction->value,
                        'delay_seconds' => 5,
                        'payload' => [
                            'grid' => 1234,
                        ],
                    ],
                ],
            ],
        ]);

        $response3->assertCreated();
        $actions = $response3->json('task.payload.actions');
        $this->assertSame('Pinewood Sawmill', $actions[0]['payload']['building_name']);
    }

    public function test_daily_scheduled_task_is_due_even_if_cron_is_delayed(): void
    {
        $engine = app(TaskSchedulerEngine::class);

        // Daily task scheduled for 14:00
        $task = ScheduledTask::create([
            'account_id' => $this->account->id,
            'task_type' => TaskType::CollectPickups,
            'schedule_type' => ScheduleType::Daily,
            'run_at_time' => '14:00',
            'status' => TaskStatus::Completed,
            'last_run_at' => Carbon::parse('2026-08-10 14:00:00'), // Last ran yesterday
            'is_active' => true,
            'payload' => ['pickup_type' => 'all'],
        ]);

        // Cron runs today at 14:15 (delayed by 15 minutes)
        $now = Carbon::parse('2026-08-11 14:15:00');

        $this->assertTrue($engine->isTaskDue($task, $now), 'Daily task should be due today even if cron is delayed by 15 minutes.');

        // Once it runs today at 14:15:00, last_run_at becomes 2026-08-11 14:15:00
        $task->update(['last_run_at' => Carbon::parse('2026-08-11 14:15:00')]);

        // Checking 5 minutes later at 14:20
        $laterNow = Carbon::parse('2026-08-11 14:20:00');
        $this->assertFalse($engine->isTaskDue($task, $laterNow), 'Daily task should NOT be due again today after running.');
    }

    public function test_manual_execution_dispatches_job_with_force_flag(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $task = ScheduledTask::create([
            'account_id' => $this->account->id,
            'task_type' => TaskType::CollectPickups,
            'schedule_type' => ScheduleType::Daily,
            'run_at_time' => '14:00',
            'status' => TaskStatus::Pending,
            'is_active' => false, // Paused task
            'payload' => ['pickup_type' => 'all'],
        ]);

        $response = $this->postJson("/api/tasks/{$task->id}/execute");

        $response->assertOk();
        $this->assertTrue($response->json('queued'));
        $this->assertSame(TaskStatus::Queued->value, $response->json('task.status'));

        Queue::assertPushed(ExecuteScheduledTaskJob::class, function (ExecuteScheduledTaskJob $job) use ($task) {
            return $job->taskId === $task->id && $job->force === true;
        });
    }
}
