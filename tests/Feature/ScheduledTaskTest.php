<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ScheduledTaskTest extends TestCase
{
    use RefreshDatabase;

    private $authMock;

    private $amfMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $parserMock = Mockery::mock(ZoneParserService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);

        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $parserMock);
    }

    public function test_can_schedule_sequence_task()
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $payload = [
            'actions' => [
                [
                    'task_type' => 'stop_production',
                    'payload' => ['grid' => 1234],
                    'delay_seconds' => 2,
                ],
                [
                    'task_type' => 'start_production',
                    'payload' => ['grid' => 1234],
                    'delay_seconds' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/tasks', [
            'name' => 'Test Sequence Name',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => $payload,
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_tasks', [
            'name' => 'Test Sequence Name',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
        ]);
    }

    public function test_executes_sequence_task_actions_with_delays()
    {
        $account = Account::create([
            'username' => 'runneruser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'runneruser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'stop_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 1,
                    ],
                    [
                        'task_type' => 'start_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andReturn('stop_response');

        $this->amfMock->shouldReceive('startProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andReturn('start_response');

        // Capture starting timestamp to verify delay happened
        $startTime = microtime(true);

        // Run the scheduler console command
        Artisan::call('tso:execute-tasks');

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Verify sleep delay of at least 1 second took place
        $this->assertGreaterThanOrEqual(1.0, $duration);

        // Verify database updates
        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('Step 1 [stop_production]: OK', $task->last_result);
        $this->assertStringContainsString('Step 2 [start_production]: OK', $task->last_result);

        // Verify BotLogs were created
        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => 'success',
            'message' => "[Task][Task#{$task->id}] Task #{$task->id}: step 1 (stop_production) completed successfully.",
        ]);
        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => 'success',
            'message' => "[Task][Task#{$task->id}] Task #{$task->id}: step 2 (start_production) completed successfully.",
        ]);
    }

    public function test_can_execute_task_manually_via_endpoint()
    {
        $account = Account::create([
            'username' => 'manualuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'manualuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 505],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);
        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->with(Mockery::any(), 505)
            ->andReturn('manual_response');

        $response = $this->postJson("/api/tasks/{$task->id}/execute");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'OK: manual_response');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertEquals('OK: manual_response', $task->last_result);
    }

    public function test_can_update_scheduled_task()
    {
        $account = Account::create([
            'username' => 'updateuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'updateuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task = ScheduledTask::create([
            'name' => 'Original Name',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'stop_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'daily',
            'run_at_time' => '08:00',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/tasks/{$task->id}", [
            'name' => 'Updated Sequence Name',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'stop_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 5,
                    ],
                    [
                        'task_type' => 'start_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'daily',
            'run_at_time' => '09:30',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('scheduled_tasks', [
            'id' => $task->id,
            'name' => 'Updated Sequence Name',
            'run_at_time' => '09:30',
        ]);
    }

    public function test_sequence_task_continues_on_step_failure_and_records_errors()
    {
        $account = Account::create([
            'username' => 'seqerroruser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'seqerroruser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'stop_production',
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 0,
                    ],
                    [
                        'task_type' => 'start_production',
                        'payload' => ['grid' => 102],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        // Step 1 fails
        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andThrow(new \Exception('Building not found on grid 101'));

        // Step 2 MUST still be executed
        $this->amfMock->shouldReceive('startProduction')
            ->once()
            ->with(Mockery::any(), 102)
            ->andReturn('start_ok');

        $service = $this->app->make(TaskExecutionService::class);
        $service->execute($task);

        $task->refresh();
        $this->assertEquals('failed', $task->status?->value);
        $this->assertArrayHasKey('step_results', $task->payload);
        $this->assertEquals('failed', $task->payload['step_results'][0]['status']);
        $this->assertStringContainsString('Building not found on grid 101', $task->payload['step_results'][0]['error']);
        $this->assertEquals('completed', $task->payload['step_results'][1]['status']);
        $this->assertNull($task->payload['step_results'][1]['error']);
    }
}
