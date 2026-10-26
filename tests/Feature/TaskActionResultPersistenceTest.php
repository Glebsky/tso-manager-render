<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Contracts\ResultAwareTaskActionHandlerInterface;
use App\Services\Tasks\Execution\TaskActionResult;
use App\Services\Tasks\TaskHandlerRegistry;
use App\Services\TsoAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TaskActionResultPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool, string}> */
    public static function cases(): array
    {
        return ['single-skip' => [false, 'skipped'], 'sequence-skip' => [true, 'skipped'],
            'single-failure' => [false, 'failed'], 'sequence-failure' => [true, 'failed'],
            'single-success' => [false, 'completed'], 'sequence-success' => [true, 'completed']];
    }

    #[DataProvider('cases')]
    public function test_semantic_result_is_persisted_without_false_success(bool $sequence, string $status): void
    {
        $message = match ($status) {
            'skipped' => 'Insufficient resources for Manuscript',
            'failed' => 'Outcome unknown; do not retry blindly',
            default => 'Manuscript queued',
        };
        $result = match ($status) {
            'skipped' => TaskActionResult::skipped($message),
            'failed' => TaskActionResult::failed($message),
            default => TaskActionResult::completed($message),
        };
        $handler = Mockery::mock(ResultAwareTaskActionHandlerInterface::class);
        $handler->shouldReceive('supports')->with('produce_buff')->andReturnTrue();
        $handler->shouldReceive('handleResult')->once()->andReturn($result);
        $handler->shouldNotReceive('handle');
        $this->app->instance(ResultAwareTaskActionHandlerInterface::class, $handler);
        $this->app->instance(TaskHandlerRegistry::class, new TaskHandlerRegistry($this->app, [ResultAwareTaskActionHandlerInterface::class]));
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once();
        $auth->shouldNotReceive('resetSession');
        $this->app->instance(TsoAuthService::class, $auth);
        $account = Account::create(['username' => 'outcome_test', 'password' => 'fixture-only', 'region' => 'ru']);
        $payload = $sequence ? ['actions' => [['task_type' => 'produce_buff', 'payload' => [], 'delay_seconds' => 0]]] : [];
        $task = ScheduledTask::create(['account_id' => $account->id, 'task_type' => $sequence ? TaskType::Sequence : TaskType::ProduceBuff,
            'payload' => $payload, 'schedule_type' => ScheduleType::Daily, 'is_active' => true, 'status' => TaskStatus::Pending]);

        app(TaskExecutionService::class)->execute($task);

        $task->refresh();
        $this->assertNotNull($task->payload);
        $this->assertNotNull($task->last_result);
        $this->assertSame($status, $task->payload['step_results'][0]['status']);
        $this->assertSame($status === 'failed' ? TaskStatus::Failed : TaskStatus::Completed, $task->status);
        $this->assertStringContainsString($message, $task->last_result);
        if ($status !== 'completed') {
            $this->assertFalse(str_starts_with($task->last_result, 'OK:'));
        }
    }
}
