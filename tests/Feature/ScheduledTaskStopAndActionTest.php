<?php

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduledTaskStopAndActionTest extends TestCase
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

    public function test_user_can_stop_running_task_via_api(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => ['buildings' => []],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::Sequence,
            'status' => TaskStatus::Running,
            'execution_token' => 'test-token-123',
            'completed_steps' => 2,
            'payload' => [
                'actions' => [
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 100]],
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 101]],
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 102]],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson("/api/tasks/{$task->id}/stop");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('task.status', 'cancelled');

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertStringContainsString('2', (string) $task->last_result);

        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => LogLevel::Warning->value,
        ]);
    }

    public function test_job_halts_immediately_when_task_status_is_cancelled(): void
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => ['buildings' => []],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::Sequence,
            'status' => TaskStatus::Cancelled,
            'execution_token' => null,
            'completed_steps' => 1,
            'payload' => [
                'actions' => [
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 100]],
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 101]],
                ],
            ],
        ]);

        $job = new ExecuteScheduledTaskJob($task->id, 'test-token', false);
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertSame(1, $task->completed_steps);
    }

    public function test_can_execute_single_action_in_sequence(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => ['buildings' => []],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::Sequence,
            'status' => TaskStatus::Pending,
            'completed_steps' => 0,
            'payload' => [
                'actions' => [
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 100]],
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 200]],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id));

        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id), 200)
            ->andReturn('dummy-response');

        $response = $this->actingAs($user)->postJson("/api/tasks/{$task->id}/actions/1/execute");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('step_result.status', 'completed');

        $task->refresh();
        $this->assertSame(0, $task->completed_steps, 'completed_steps must not advance on single action run');
        $payload = $task->payload ?? [];
        $this->assertSame('completed', $payload['step_results'][1]['status'] ?? null);
    }

    public function test_cannot_execute_single_action_when_task_is_busy(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => ['buildings' => []],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::Sequence,
            'status' => TaskStatus::Running,
            'completed_steps' => 0,
            'payload' => [
                'actions' => [
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 100]],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson("/api/tasks/{$task->id}/actions/0/execute");

        $response->assertStatus(422);
    }
}
