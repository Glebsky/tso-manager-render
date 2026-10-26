<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ActionOutcomeException;
use App\Exceptions\ActionSkippedException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Contracts\ResultAwareTaskActionHandlerInterface;
use App\Services\Tasks\Execution\ActionRetryPolicy;
use App\Services\Tasks\Execution\GameResponseValidator;
use App\Services\Tasks\Execution\SingleActionExecutor;
use App\Services\Tasks\Execution\StepOutcome;
use App\Services\Tasks\Execution\StepResultCollection;
use App\Services\Tasks\Execution\StepRunner;
use App\Services\Tasks\Execution\TaskActionResult;
use App\Services\Tasks\TaskActivityLogger;
use App\Services\Tasks\TaskHandlerRegistry;
use Mockery;
use Tests\TestCase;

final class TaskActionOutcomeTest extends TestCase
{
    private function executor(TaskActionResult $result): SingleActionExecutor
    {
        $handler = Mockery::mock(ResultAwareTaskActionHandlerInterface::class);
        $handler->shouldReceive('supports')->with('produce_buff')->andReturnTrue();
        $handler->shouldNotReceive('handle');
        $handler->shouldReceive('handleResult')->once()->andReturn($result);
        $this->app->instance(ResultAwareTaskActionHandlerInterface::class, $handler);
        $registry = new TaskHandlerRegistry($this->app, [ResultAwareTaskActionHandlerInterface::class]);

        return new SingleActionExecutor($registry, app(GameResponseValidator::class), app(ActionRetryPolicy::class));
    }

    public function test_resource_refusal_is_skipped_with_reason_in_sequence_and_summary(): void
    {
        $message = (string) __('tasks.produce_buff.rejected.insufficient_resources', ['grid' => 10, 'recipe' => 'Manuscript', 'type' => 2]);
        $logger = new TaskActivityLogger;
        $runner = new StepRunner($this->executor(TaskActionResult::skipped($message)), $logger);
        $account = new Account;
        $account->id = 1;
        $task = new ScheduledTask;
        $task->id = 2;
        $outcome = $runner->run($task, $account, 0, ['task_type' => 'produce_buff']);
        $this->assertTrue($outcome->isSkipped());
        $this->assertSame($message, $outcome->toArray()['reason']);
        $this->assertStringContainsString($message, $outcome->summaryLine());
        $collection = new StepResultCollection;
        $collection->add($outcome);
        $this->assertFalse($collection->hasSuccess());
        $this->assertTrue($collection->hasSkipped());
    }

    public function test_unknown_post_send_result_is_failure_and_is_not_retried(): void
    {
        $this->expectException(ActionOutcomeException::class);
        $this->expectExceptionMessage('Outcome unknown; no automatic retry');
        $this->executor(TaskActionResult::failed('Outcome unknown; no automatic retry'))
            ->executeWithRetry(new Account, 'produce_buff', []);
    }

    public function test_single_action_reports_skip_instead_of_success(): void
    {
        $this->expectException(ActionSkippedException::class);
        $this->expectExceptionMessage('Insufficient resources');
        $this->executor(TaskActionResult::skipped('Insufficient resources'))
            ->executeWithRetry(new Account, 'produce_buff', []);
    }

    public function test_success_message_survives_persistence_and_summary(): void
    {
        $before = StepOutcome::completed(0, 'produce_buff', 'Manuscript queued');
        $after = StepOutcome::fromArray(0, 'produce_buff', $before->toArray());
        $this->assertSame('Manuscript queued', $after->message);
        $this->assertStringContainsString('Manuscript queued', $after->summaryLine());
        $this->assertNull(StepOutcome::fromArray(0, 'produce_buff', ['status' => 'completed'])->message);
    }

    public function test_logging_failure_does_not_turn_completed_command_into_failed_step(): void
    {
        $logger = new TaskActivityLogger;
        // In-memory database has no bot_logs table: exercise a real persistence failure.
        $runner = new StepRunner($this->executor(TaskActionResult::completed('Manuscript queued')), $logger);
        $account = new Account;
        $account->id = 1;
        $task = new ScheduledTask;
        $task->id = 2;
        $result = $runner->run($task, $account, 0, ['task_type' => 'produce_buff']);
        $this->assertTrue($result->isCompleted());
        $this->assertSame('Manuscript queued', $result->message);
    }
}
