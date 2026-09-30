<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * A sequence step followed by a delay longer than the job's time budget is
 * handed off to a delayed job. The hand-off must keep the run alive
 * (heartbeat pushed past the wait, FR-007) and must not re-dispatch a run
 * that was stopped concurrently.
 */
class ExecuteScheduledTaskJobHandOffTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $amfMock;

    protected function setUp(): void
    {
        parent::setUp();

        $authMock = Mockery::mock(TsoAuthService::class);
        $authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
        $this->app->instance(TsoAuthService::class, $authMock);

        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
    }

    private function createSequenceTask(): ScheduledTask
    {
        $account = Account::create([
            'username' => 'handoff_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'handoff_user',
        ]);

        return ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Hand-off',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 60,
            'is_active' => true,
            'status' => TaskStatus::Queued,
            'execution_token' => 'handoff-token',
            'heartbeat_at' => Carbon::now(),
            'payload' => [
                'actions' => [
                    ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 100],
                    ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
                ],
            ],
        ]);
    }

    public function test_hand_off_pushes_heartbeat_past_the_delay_and_dispatches_delayed_job(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00'));

        $task = $this->createSequenceTask();

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');

        $job = new ExecuteScheduledTaskJob($task->id, 'handoff-token');
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $this->assertNotNull($task->heartbeat_at);
        $this->assertSame(Carbon::now()->addSeconds(100)->timestamp, $task->heartbeat_at->timestamp);
        $this->assertSame('handoff-token', $task->execution_token);

        Queue::assertPushed(ExecuteScheduledTaskJob::class, function (ExecuteScheduledTaskJob $pushed) use ($task) {
            return $pushed->taskId === $task->id
                && $pushed->executionToken === 'handoff-token'
                && $pushed->delay !== null;
        });
    }

    public function test_hand_off_does_not_redispatch_when_run_was_stopped_after_the_step(): void
    {
        Queue::fake();

        $task = $this->createSequenceTask();
        $stateMachine = $this->app->make(TaskStateMachine::class);

        $this->amfMock->shouldReceive('stopProduction')
            ->once()
            ->andReturnUsing(function () use ($task, $stateMachine) {
                // User presses Stop while the game command is in flight.
                $stateMachine->cancel($task, 'stopped');

                return 'ok';
            });

        $job = new ExecuteScheduledTaskJob($task->id, 'handoff-token');
        $this->app->call([$job, 'handle']);

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);

        Queue::assertNothingPushed();
    }
}
