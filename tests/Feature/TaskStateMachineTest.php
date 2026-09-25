<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\Tasks\ScheduledTaskService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private TaskStateMachine $stateMachine;

    private ScheduledTaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = $this->app->make(TaskStateMachine::class);
        $this->taskService = $this->app->make(ScheduledTaskService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(array $attributes = []): ScheduledTask
    {
        $account = Account::create([
            'username' => 'sm_user_'.uniqid(),
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sm_user',
        ]);

        return ScheduledTask::create(array_merge([
            'account_id' => $account->id,
            'name' => 'State Machine Test Task',
            'task_type' => 'stop_production',
            'schedule_type' => ScheduleType::Interval,
            'interval_minutes' => 30,
            'is_active' => true,
            'status' => TaskStatus::Pending,
            'payload' => ['grid' => 100],
        ], $attributes));
    }

    public function test_reserve_transitions_idle_task_to_queued_with_token(): void
    {
        $task = $this->createTask(['status' => TaskStatus::Pending]);

        $reserved = $this->stateMachine->reserve($task, 'token-123');

        $this->assertTrue($reserved);
        $task->refresh();
        $this->assertSame(TaskStatus::Queued, $task->status);
        $this->assertSame('token-123', $task->execution_token);
        $this->assertNotNull($task->queued_at);
        $this->assertSame(0, $task->completed_steps);
    }

    public function test_reserve_fails_when_task_is_already_queued_or_running(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'active-token',
        ]);

        $reserved = $this->stateMachine->reserve($task, 'new-token');

        $this->assertFalse($reserved);
        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);
        $this->assertSame('active-token', $task->execution_token);
    }

    public function test_reserve_fails_for_inactive_task_unless_forced(): void
    {
        $task = $this->createTask([
            'is_active' => false,
            'status' => TaskStatus::Pending,
        ]);

        $reservedWithoutForce = $this->stateMachine->reserve($task, 'token-1', force: false);
        $this->assertFalse($reservedWithoutForce);

        $reservedWithForce = $this->stateMachine->reserve($task, 'token-2', force: true);
        $this->assertTrue($reservedWithForce);

        $task->refresh();
        $this->assertSame(TaskStatus::Queued, $task->status);
        $this->assertSame('token-2', $task->execution_token);
    }

    public function test_start_transitions_queued_task_to_running_with_valid_token(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Queued,
            'execution_token' => 'token-abc',
        ]);

        $started = $this->stateMachine->start($task, 'token-abc');

        $this->assertTrue($started);
        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);
    }

    public function test_start_fails_when_task_was_cancelled_between_guard_and_start(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Queued,
            'execution_token' => 'token-abc',
        ]);

        // User stops the task concurrently
        $this->stateMachine->cancel($task, 'Stopped by user');
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);

        // Job attempts start with old token
        $started = $this->stateMachine->start($task, 'token-abc');

        $this->assertFalse($started);
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
    }

    public function test_progress_updates_completed_steps_only_with_valid_token(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'token-seq',
            'completed_steps' => 1,
            'payload' => ['actions' => [['step' => 1], ['step' => 2]]],
        ]);

        $staleUpdated = $this->stateMachine->progress($task, 'wrong-token', 2, ['actions' => []]);
        $this->assertFalse($staleUpdated);
        $task->refresh();
        $this->assertSame(1, $task->completed_steps);

        $validUpdated = $this->stateMachine->progress($task, 'token-seq', 2, ['actions' => []]);
        $this->assertTrue($validUpdated);
        $task->refresh();
        $this->assertSame(2, $task->completed_steps);
    }

    public function test_finish_transitions_running_to_completed_and_resets_token(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'token-fin',
            'completed_steps' => 2,
        ]);

        $finished = $this->stateMachine->finish(
            $task,
            'token-fin',
            TaskStatus::Completed,
            'Execution summary',
            ['actions' => []]
        );

        $this->assertTrue($finished);
        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertSame(0, $task->completed_steps);
        $this->assertSame('Execution summary', $task->last_result);
        $this->assertNotNull($task->last_run_at);
    }

    public function test_finish_fails_and_does_not_overwrite_when_task_was_cancelled(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'token-race',
        ]);

        // Task is stopped by user
        $this->stateMachine->cancel($task, 'User stopped');
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);

        // Late worker process attempts finish
        $finished = $this->stateMachine->finish(
            $task,
            'token-race',
            TaskStatus::Completed,
            'Late OK',
            []
        );

        $this->assertFalse($finished);
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertStringContainsString('User stopped', (string) $task->last_result);
    }

    public function test_cancel_when_busy_sets_last_run_at(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'running-token',
            'last_run_at' => Carbon::now()->subHours(2),
        ]);

        $cancelled = $this->stateMachine->cancel($task, 'Cancelled during run');

        $this->assertTrue($cancelled);
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertNotNull($task->last_run_at);
        $this->assertTrue($task->last_run_at->isAfter(Carbon::now()->subMinute()));
    }

    public function test_cancel_when_idle_does_not_shift_schedule(): void
    {
        $baselineTime = Carbon::now()->subHours(5);
        $task = $this->createTask([
            'status' => TaskStatus::Completed,
            'execution_token' => null,
            'last_run_at' => $baselineTime,
        ]);

        $cancelled = $this->stateMachine->cancel($task, 'Stopped idle');

        $this->assertTrue($cancelled);
        $task->refresh();
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertNotNull($task->last_run_at);
        $this->assertSame($baselineTime->timestamp, $task->last_run_at->timestamp);
    }

    public function test_fail_job_marks_failed_only_if_token_matches(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'job-token',
        ]);

        $staleFail = $this->stateMachine->failJob($task, 'outdated-token', 'Job timeout');
        $this->assertFalse($staleFail);
        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);

        $validFail = $this->stateMachine->failJob($task, 'job-token', 'Job timeout');
        $this->assertTrue($validFail);
        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertNull($task->execution_token);
    }

    public function test_manual_execute_on_busy_task_throws_409_conflict(): void
    {
        $task = $this->createTask([
            'status' => TaskStatus::Running,
            'execution_token' => 'busy-token',
        ]);

        try {
            $this->taskService->execute($task);
            $this->fail('Expected TaskExecutionException was not thrown');
        } catch (TaskExecutionException $e) {
            $this->assertSame(409, $e->httpStatus());
            $this->assertSame('error.task_busy', $e->getTranslationKey());
        }

        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);
        $this->assertSame('busy-token', $task->execution_token);
    }
}
