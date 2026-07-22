<?php

namespace Tests\Feature;

use App\Jobs\ExecuteScheduledTaskJob;
use App\Jobs\MarketSyncJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\Setting;
use App\Services\TaskExecutionService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SchedulerArchitectureTest extends TestCase
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

    public function test_scheduler_reserves_due_tasks_atomically_and_dispatches_to_tso_tasks_queue()
    {
        Queue::fake();

        $account = Account::create([
            'username' => 'scheduler_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'scheduler_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'status' => 'pending',
        ]);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        $task->refresh();
        $this->assertEquals('queued', $task->status);
        $this->assertNotNull($task->execution_token);
        $this->assertNotNull($task->queued_at);

        Queue::assertPushedOn('tso-tasks', ExecuteScheduledTaskJob::class, function ($job) use ($task) {
            return $job->taskId === $task->id && $job->executionToken === $task->execution_token;
        });
    }

    public function test_concurrent_scheduler_runs_do_not_duplicate_dispatch()
    {
        Queue::fake();

        $account = Account::create([
            'username' => 'dedup_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'dedup_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'status' => 'pending',
        ]);

        // First run reserves task
        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);
        $token1 = $task->fresh()->execution_token;

        // Second concurrent run should skip already queued task
        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);
        $token2 = $task->fresh()->execution_token;

        $this->assertEquals($token1, $token2);

        // Job pushed only once
        Queue::assertPushed(ExecuteScheduledTaskJob::class, 1);
    }

    public function test_job_skips_execution_if_token_mismatch_or_inactive()
    {
        $account = Account::create([
            'username' => 'token_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'token_user',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => false, // Inactive
            'status' => 'queued',
            'execution_token' => 'valid-token',
        ]);

        $this->amfMock->shouldNotReceive('stopProduction');

        $job = new ExecuteScheduledTaskJob($task->id, 'invalid-token');
        $job->handle($this->app->make(TaskExecutionService::class));
    }

    public function test_sequence_task_step_idempotency_resumes_from_failed_step()
    {
        $account = Account::create([
            'username' => 'seq_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'seq_user',
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
                        'payload' => ['grid' => 101],
                        'delay_seconds' => 0,
                    ],
                ],
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'status' => 'pending',
            'completed_steps' => 1, // Step 1 (stop_production) was already completed in a prior attempt
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);

        // Step 1 stopProduction should NOT be called again
        $this->amfMock->shouldNotReceive('stopProduction');

        // Step 2 startProduction SHOULD be called
        $this->amfMock->shouldReceive('startProduction')
            ->once()
            ->with(Mockery::any(), 101)
            ->andReturn('start_ok');

        $service = $this->app->make(TaskExecutionService::class);
        $service->execute($task);

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertStringContainsString('Step 1 [stop_production]: SKIPPED (already executed)', $task->last_result);
        $this->assertStringContainsString('Step 2 [start_production]: OK', $task->last_result);
    }

    public function test_market_sync_atomic_lock_prevents_duplicate_sync()
    {
        Queue::fake();

        $account = Account::create([
            'username' => 'market_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'market_user',
        ]);

        \App\Models\MarketServerConnection::create([
            'server_id' => 'ru_test',
            'locale' => 'RU',
            'display_name' => 'RU Market',
            'account_id' => $account->id,
            'sync_status' => 'connected',
        ]);

        Setting::set('market_sync_interval', '15');

        // Pre-acquire lock to simulate another process executing market sync
        Cache::add('market_sync_lock:server:ru_test', true, 180);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        // MarketSyncJob should NOT be dispatched because lock is held
        Queue::assertNotPushed(MarketSyncJob::class);
    }
}
