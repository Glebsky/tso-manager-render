<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
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

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
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
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => $payload,
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_tasks', [
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
            ->andReturn('amf_stop_response');

        $this->amfMock->shouldReceive('startProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andReturn('amf_start_response');

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
            'message' => "Sequence task #{$task->id} step 1 [stop_production] executed successfully.",
        ]);
        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => 'success',
            'message' => "Sequence task #{$task->id} step 2 [start_production] executed successfully.",
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
            ->andReturn('manual_amf_response');

        $response = $this->postJson("/api/tasks/{$task->id}/execute");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'OK: manual_amf_response');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertEquals('OK: manual_amf_response', $task->last_result);
    }
}
