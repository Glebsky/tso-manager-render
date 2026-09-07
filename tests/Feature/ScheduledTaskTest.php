<?php

namespace Tests\Feature;

use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\Contracts\MineCommandGatewayInterface;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\ZoneSnapshot;
use App\Services\TaskExecutionService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduledTaskTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $authMock;

    private MockInterface $amfMock;

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

    public function test_can_schedule_sequence_task(): void
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

    public function test_executes_sequence_task_actions_with_delays(): void
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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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
        $this->assertStringContainsString('Step 1 [stop_production]: OK', (string) $task->last_result);
        $this->assertStringContainsString('Step 2 [start_production]: OK', (string) $task->last_result);

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

    public function test_can_execute_task_manually_via_endpoint(): void
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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();
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

    public function test_can_update_scheduled_task(): void
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

    public function test_sequence_task_continues_on_step_failure_and_records_errors(): void
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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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
        $this->assertEquals('failed', $task->status->value);
        $this->assertIsArray($task->payload);
        $this->assertArrayHasKey('step_results', $task->payload);
        $stepResults = $task->payload['step_results'];
        $this->assertIsArray($stepResults);
        $this->assertEquals('failed', $stepResults[0]['status']);
        $this->assertStringContainsString('Building not found on grid 101', (string) $stepResults[0]['error']);
        $this->assertEquals('completed', $stepResults[1]['status']);
        $this->assertNull($stepResults[1]['error']);
    }

    public function test_sequence_task_error_log_is_not_truncated_to_100_chars_and_formats_clean_error(): void
    {
        $account = Account::create([
            'username' => 'longerroruser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'longerroruser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->shouldReceive('forAccount')
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [202 => new DepositSnapshot(grid: 202, name: 'IronOre', amount: 500, maxAmount: 500)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 0, total: 3),
            ));
        $this->app->instance(ZoneSnapshotProviderInterface::class, $mockZones);

        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldReceive('buildMine')
            ->once()
            ->andThrow(new TaskExecutionException('build_mine.game_error', [
                'message' => 'This is a very long and detailed error message describing that building a copper mine on grid 202 failed due to an invalid deposit state',
            ]));
        $this->app->instance(MineCommandGatewayInterface::class, $mockGateway);

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
                        'task_type' => 'build_mine',
                        'payload' => ['grid' => 202],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andReturn('ok');

        $service = $this->app->make(TaskExecutionService::class);
        $service->execute($task);

        $task->refresh();
        $this->assertEquals('failed', $task->status->value);

        // Verify summary contains clean human-readable text and is not truncated to 100 characters
        $this->assertStringNotContainsString('{"key":"tasks.build_mine.game_error"', (string) $task->last_result);
        $this->assertStringContainsString('This is a very long and detailed error message', (string) $task->last_result);

        // Verify bot_logs entry is not truncated to 100 characters
        $log = BotLog::where('account_id', $account->id)
            ->where('message', 'like', '%finished with errors%')
            ->first();

        $this->assertNotNull($log);
        $this->assertGreaterThan(100, strlen($log->message));
        $this->assertStringContainsString('This is a very long and detailed error message', $log->message);
        $this->assertStringNotContainsString('{"key":"tasks.build_mine.game_error"', $log->message);
    }

    public function test_invalid_non_numeric_task_parameter_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks/undefined/execute')
            ->assertStatus(404);

        $this->actingAs($user)
            ->getJson('/api/tasks/undefined/status')
            ->assertStatus(404);

        $this->actingAs($user)
            ->postJson('/api/tasks/undefined/toggle')
            ->assertStatus(404);
    }

    public function test_can_duplicate_scheduled_task(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'dupuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'dupuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $task1 = ScheduledTask::create([
            'name' => 'Original Task',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => ['actions' => [['task_type' => 'stop_production', 'payload' => ['grid' => 10]]]],
            'schedule_type' => 'daily',
            'run_at_time' => '10:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $task2 = ScheduledTask::create([
            'name' => 'Next Task',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => ['actions' => [['task_type' => 'start_production', 'payload' => ['grid' => 10]]]],
            'schedule_type' => 'daily',
            'run_at_time' => '11:00',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/tasks/{$task1->id}/duplicate");

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Original Task (копия)');
        $response->assertJsonPath('data.is_active', false);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.sort_order', 2);

        $task2->refresh();
        $this->assertSame(3, $task2->sort_order);

        $this->assertDatabaseHas('scheduled_tasks', [
            'name' => 'Original Task (копия)',
            'account_id' => $account->id,
            'is_active' => false,
            'sort_order' => 2,
        ]);
    }

    public function test_can_reorder_scheduled_tasks(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'reorderuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'reorderuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        $t1 = ScheduledTask::create([
            'name' => 'Task A',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [],
            'schedule_type' => 'daily',
            'sort_order' => 1,
        ]);

        $t2 = ScheduledTask::create([
            'name' => 'Task B',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [],
            'schedule_type' => 'daily',
            'sort_order' => 2,
        ]);

        $t3 = ScheduledTask::create([
            'name' => 'Task C',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'payload' => [],
            'schedule_type' => 'daily',
            'sort_order' => 3,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/tasks/reorder', [
                'task_ids' => [$t3->id, $t1->id, $t2->id],
            ]);

        $response->assertStatus(200);

        $t1->refresh();
        $t2->refresh();
        $t3->refresh();

        $this->assertSame(1, $t3->sort_order);
        $this->assertSame(2, $t1->sort_order);
        $this->assertSame(3, $t2->sort_order);

        $listResponse = $this->actingAs($user)->getJson('/api/tasks');
        $listResponse->assertStatus(200);
        $data = $listResponse->json('data');
        $this->assertSame($t3->id, $data[0]['id']);
        $this->assertSame($t1->id, $data[1]['id']);
        $this->assertSame($t2->id, $data[2]['id']);
    }

    public function test_reorder_validation_fails_with_invalid_ids(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tasks/reorder', [
                'task_ids' => [999999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids.0']);
    }
}
