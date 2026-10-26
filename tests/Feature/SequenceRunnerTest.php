<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\BuffUnavailableException;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\Tasks\Execution\SequenceRunner;
use App\Services\Tasks\Execution\StepOutcome;
use App\Services\Tasks\Execution\StepResultCollection;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\Tasks\TaskHandlerRegistry;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * WP-09 — one sequence engine for the inline and the queue path (A-12, B-11).
 */
class SequenceRunnerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $amfMock;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $authMock = Mockery::mock(TsoAuthService::class);
        $authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
        $this->app->instance(TsoAuthService::class, $authMock);

        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->app->instance(TsoAmfService::class, $this->amfMock);

        $this->account = Account::create([
            'username' => 'seq_runner_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'seq_runner_user',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(array $actions, array $attributes = []): ScheduledTask
    {
        return ScheduledTask::create(array_merge([
            'account_id' => $this->account->id,
            'name' => 'Seq',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 60,
            'is_active' => true,
            'status' => TaskStatus::Queued,
            'execution_token' => 'seq-token',
            'payload' => ['actions' => $actions],
        ], $attributes));
    }

    private function runner(): SequenceRunner
    {
        return $this->app->make(SequenceRunner::class);
    }

    public function test_zero_delay_steps_never_hand_off_even_with_an_exhausted_budget(): void
    {
        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');
        $this->amfMock->shouldReceive('startProduction')->once()->andReturn('ok');

        $result = $this->runner()->run($task, $this->account, 'seq-token', budgetSeconds: 0);

        $this->assertTrue($result->isFinished());

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertSame('OK: Step 1 [stop_production]: OK — ok; Step 2 [start_production]: OK — ok', $task->last_result);
    }

    public function test_short_delay_is_waited_inline_with_the_delay_waiter(): void
    {
        Sleep::fake();

        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 3],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');
        $this->amfMock->shouldReceive('startProduction')->once()->andReturn('ok');

        $result = $this->runner()->run($task, $this->account, 'seq-token', budgetSeconds: 45);

        $this->assertTrue($result->isFinished());
        Sleep::assertSleptTimes(3);
    }

    public function test_long_delay_hands_off_with_the_remaining_delay(): void
    {
        Sleep::fake();

        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 100],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');
        $this->amfMock->shouldNotReceive('startProduction');

        $result = $this->runner()->run($task, $this->account, 'seq-token', budgetSeconds: 45);

        $this->assertTrue($result->isHandOff());
        $this->assertSame(100, $result->nextDelay);
        Sleep::assertNeverSlept();

        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);
        $this->assertSame('seq-token', $task->execution_token);
        $this->assertSame(1, $task->completed_steps);
        $this->assertSame('completed', $this->stepResults($task)[0]['status']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepResults(ScheduledTask $task): array
    {
        $payload = $task->payload ?? [];
        $this->assertArrayHasKey('step_results', $payload);

        return $payload['step_results'];
    }

    public function test_inline_run_without_budget_waits_instead_of_handing_off(): void
    {
        Sleep::fake();

        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 100],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');
        $this->amfMock->shouldReceive('startProduction')->once()->andReturn('ok');

        $result = $this->runner()->run($task, $this->account, 'seq-token');

        $this->assertTrue($result->isFinished());
        Sleep::assertSleptTimes(100);
    }

    public function test_resumed_run_after_hand_off_produces_the_same_summary_as_an_inline_run(): void
    {
        Queue::fake();

        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 100],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturn('ok');
        $this->amfMock->shouldReceive('startProduction')->once()->andReturn('ok');

        // First job: step 1, then hand-off.
        $this->app->call([new ExecuteScheduledTaskJob($task->id, 'seq-token'), 'handle']);
        Queue::assertPushed(ExecuteScheduledTaskJob::class, 1);

        // Second (delayed) job resumes from completed_steps = 1.
        $this->app->call([new ExecuteScheduledTaskJob($task->id, 'seq-token'), 'handle']);

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertSame('OK: Step 1 [stop_production]: OK — ok; Step 2 [start_production]: OK — ok', $task->last_result);
    }

    public function test_stop_during_a_step_aborts_the_run_without_overwriting_the_cancelled_state(): void
    {
        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
            ['task_type' => 'start_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
        ]);
        $stateMachine = $this->app->make(TaskStateMachine::class);

        $this->amfMock->shouldReceive('stopProduction')->once()->andReturnUsing(function () use ($task, $stateMachine): string {
            $stateMachine->cancel($task, 'stopped');

            return 'ok';
        });
        $this->amfMock->shouldNotReceive('startProduction');

        $result = $this->runner()->run($task, $this->account, 'seq-token');

        $this->assertFalse($result->isFinished());
        $this->assertFalse($result->isHandOff());

        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);
    }

    public function test_failed_and_skipped_steps_are_results_not_exceptions(): void
    {
        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1], 'delay_seconds' => 0],
            ['task_type' => 'apply_buff', 'payload' => ['grid' => 2, 'buff_name' => 'Fish Platter'], 'delay_seconds' => 0],
            ['task_type' => 'start_production', 'payload' => ['grid' => 3], 'delay_seconds' => 0],
        ]);

        // A scripted handler set: step 1 fails, step 2 is skippable, step 3 succeeds.
        $this->app->instance(TaskHandlerRegistry::class, new TaskHandlerRegistry($this->app, [ScriptedSequenceHandler::class]));

        $result = $this->runner()->run($task, $this->account, 'seq-token');

        $this->assertTrue($result->isFinished());

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $results = $this->stepResults($task);
        $this->assertSame('failed', $results[0]['status']);
        $this->assertSame('boom', $results[0]['error']);
        $this->assertSame('skipped', $results[1]['status']);
        $this->assertSame('Fish Platter', $results[1]['buff']);
        $this->assertNotSame('', $results[1]['reason']);
        $this->assertSame('completed', $results[2]['status']);

        $this->assertStringStartsWith('PARTIAL:', (string) $task->last_result);
        $this->assertStringContainsString('Step 1 [stop_production]: ERROR - boom', (string) $task->last_result);
        $this->assertStringContainsString('Step 2 [apply_buff]: SKIPPED (buff Fish Platter missing)', (string) $task->last_result);
        $this->assertStringContainsString('Step 3 [start_production]: OK', (string) $task->last_result);
    }

    public function test_manual_single_step_uses_the_same_step_runner(): void
    {
        $task = $this->createTask([
            ['task_type' => 'stop_production', 'payload' => ['grid' => 1]],
            ['task_type' => 'stop_production', 'payload' => ['grid' => 2]],
        ], ['status' => TaskStatus::Pending, 'execution_token' => null]);

        $this->amfMock->shouldReceive('stopProduction')->once()->with(Mockery::any(), 2)->andThrow(new \RuntimeException('manual boom'));

        $result = $this->app->make(TaskExecutionService::class)->executeSingleSequenceAction($task, 1);

        $this->assertSame(['status' => 'failed', 'error' => 'manual boom'], $result);

        $task->refresh();
        $this->assertSame(0, $task->completed_steps);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame('failed', $this->stepResults($task)[1]['status']);
        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $this->account->id,
            'message' => "[Task][Task#{$task->id}] Task \"Seq\": single action 2 (stop_production) failed: manual boom",
        ]);
    }

    public function test_step_result_collection_round_trips_through_the_persisted_shape(): void
    {
        $actions = [
            ['task_type' => 'a'],
            ['task_type' => 'b'],
            ['task_type' => 'c'],
            ['task_type' => 'd'],
        ];

        $raw = [
            StepOutcome::alreadyDone(0, 'a')->toArray(),
            StepOutcome::completed(1, 'b')->toArray(),
            StepOutcome::skipped(2, 'c', 'no fish', 'Fish')->toArray(),
            StepOutcome::failed(3, 'd', json_encode(['key' => 'x', 'message' => 'readable'], JSON_THROW_ON_ERROR))->toArray(),
        ];

        $collection = StepResultCollection::fromPayload($actions, $raw);

        $this->assertTrue($collection->hasFailure());
        $this->assertTrue($collection->hasSuccess());
        $this->assertTrue($collection->hasSkipped());
        $this->assertSame(TaskStatus::Failed, $collection->finalStatus());
        $this->assertSame(
            'Step 1 [a]: SKIPPED (already executed); Step 2 [b]: OK; Step 3 [c]: SKIPPED (buff Fish missing); Step 4 [d]: ERROR - readable',
            $collection->summary()
        );
        $this->assertStringStartsWith('PARTIAL:', $collection->formattedSummary());

        // Only already-done steps: no success, no failure -> plain OK.
        $onlyResumed = StepResultCollection::fromPayload($actions, [StepOutcome::alreadyDone(0, 'a')->toArray()]);
        $this->assertFalse($onlyResumed->hasSuccess());
        $this->assertStringStartsWith('OK:', $onlyResumed->formattedSummary());
    }
}

/**
 * Handler used by test_failed_and_skipped_steps_are_results_not_exceptions.
 */
final class ScriptedSequenceHandler implements TaskActionHandlerInterface
{
    public function supports(string $actionType): bool
    {
        return true;
    }

    public function handle(Account $account, array $payload): string
    {
        return match ((int) ($payload['grid'] ?? 0)) {
            1 => throw new \RuntimeException('boom'),
            2 => throw new BuffUnavailableException((string) $payload['buff_name']),
            default => 'ok',
        };
    }
}
