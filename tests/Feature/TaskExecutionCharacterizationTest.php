<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TaskExecutionCharacterizationTest extends TestCase
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

    public function test_throws_inactive_exception_when_task_is_paused_unless_forced(): void
    {
        $account = Account::create([
            'username' => 'inactive_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'inactive_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::StopProduction,
            'payload' => ['grid' => 100],
            'schedule_type' => ScheduleType::Once,
            'is_active' => false,
            'status' => TaskStatus::Pending,
        ]);

        $service = $this->app->make(TaskExecutionService::class);

        $this->expectException(TaskInactiveException::class);
        $service->execute($task);
    }

    public function test_allows_forced_execution_of_inactive_task(): void
    {
        $account = Account::create([
            'username' => 'forced_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'forced_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::StopProduction,
            'payload' => ['grid' => 100],
            'schedule_type' => ScheduleType::Once,
            'is_active' => false,
            'status' => TaskStatus::Pending,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);
        $this->amfMock->shouldReceive('stopProduction')->once()->with(Mockery::any(), 100)->andReturn('stop_ok');

        $service = $this->app->make(TaskExecutionService::class);
        $result = $service->execute($task, null, true);

        $this->assertEquals('stop_ok', $result);
        $task->refresh();
        $this->assertEquals(TaskStatus::Completed, $task->status);
    }

    public function test_throws_token_mismatch_exception_when_expected_token_differs(): void
    {
        $account = Account::create([
            'username' => 'token_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'token_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::StopProduction,
            'payload' => ['grid' => 100],
            'schedule_type' => ScheduleType::Once,
            'is_active' => true,
            'status' => TaskStatus::Queued,
            'execution_token' => 'correct-token',
        ]);

        $service = $this->app->make(TaskExecutionService::class);

        $this->expectException(TokenMismatchException::class);
        $service->execute($task, 'wrong-token');
    }

    public function test_deactivates_once_schedule_task_upon_completion(): void
    {
        $account = Account::create([
            'username' => 'once_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'once_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::StopProduction,
            'payload' => ['grid' => 101],
            'schedule_type' => ScheduleType::Once,
            'is_active' => true,
            'status' => TaskStatus::Pending,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);
        $this->amfMock->shouldReceive('stopProduction')->once()->with(Mockery::any(), 101)->andReturn('stop_ok');

        $service = $this->app->make(TaskExecutionService::class);
        $service->execute($task);

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertEquals(TaskStatus::Completed, $task->status);
        $this->assertNull($task->execution_token);
    }

    public function test_retries_session_errors_1005_and_1012_with_relogin(): void
    {
        $account = Account::create([
            'username' => 'retry_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'retry_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => TaskType::StopProduction,
            'payload' => ['grid' => 102],
            'schedule_type' => ScheduleType::Daily,
            'is_active' => true,
            'status' => TaskStatus::Pending,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);
        $this->authMock->shouldReceive('resetSession')->once()->with(Mockery::any());
        $this->authMock->shouldReceive('getCookieFile')->andReturn(storage_path('app/cookies/test.txt'));
        $this->authMock->shouldReceive('login')->once()->with(Mockery::any())->andReturn([]);

        $this->amfMock->shouldReceive('stopProduction')
            ->twice()
            ->andReturnUsing(function () {
                static $attempts = 0;
                $attempts++;
                if ($attempts === 1) {
                    throw new GameServerErrorException(1005, 'Session expired');
                }

                return 'stop_ok_retry';
            });

        $this->amfMock->shouldReceive('invalidateSession')->once();

        $service = $this->app->make(TaskExecutionService::class);
        $result = $service->execute($task);

        $this->assertEquals('stop_ok_retry', $result);
        $task->refresh();
        $this->assertEquals(TaskStatus::Completed, $task->status);
    }
}
