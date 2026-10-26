<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Execution\StepOutcome;
use App\Services\Tasks\Execution\TaskResultSummary;
use App\Services\Tasks\Handlers\SendSpecialistHandler;
use App\Services\Tasks\TaskHandlerRegistry;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BinaryTaskResultPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function executionPaths(): array
    {
        return ['sequence' => ['sequence'], 'manual-step' => ['manual'], 'single-task' => ['single']];
    }

    #[DataProvider('executionPaths')]
    public function test_binary_specialist_response_never_replaces_definition_or_breaks_json(string $path): void
    {
        $binary = "\x00\x03\xff\x80_amf_response";
        $amf = Mockery::mock(TsoAmfService::class);
        $amf->shouldReceive('sendSpecialist')->once()->andReturn($binary);
        $this->app->instance(TsoAmfService::class, $amf);
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with($binary)->once()->andReturn(['errorCode' => 0]);
        $this->app->instance(ZoneParserService::class, $parser);
        $this->app->instance(TaskHandlerRegistry::class, new TaskHandlerRegistry($this->app, [SendSpecialistHandler::class]));
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once();
        $auth->shouldNotReceive('login');
        $this->app->instance(TsoAuthService::class, $auth);
        $account = Account::create(['username' => 'binary_fixture', 'password' => 'fixture-only', 'region' => 'ru']);
        $action = ['task_type' => 'send_specialist', 'payload' => ['task_type' => 1, 'sub_task_id' => 2, 'unique_id1' => 3, 'unique_id2' => 4], 'delay_seconds' => 0, 'meta' => ['name' => 'Геолог — руда']];
        $payload = $path === 'single' ? $action['payload'] : ['actions' => [$action]];
        $task = ScheduledTask::create(['account_id' => $account->id, 'task_type' => $path === 'single' ? TaskType::SendSpecialist : TaskType::Sequence,
            'payload' => $payload, 'schedule_type' => ScheduleType::Daily, 'is_active' => true, 'status' => TaskStatus::Pending]);
        $service = app(TaskExecutionService::class);
        if ($path === 'manual') {
            $response = $this->actingAs(User::factory()->create())
                ->postJson("/api/tasks/{$task->id}/actions/0/execute");
            $response->assertOk()->assertJsonPath('step_result.status', 'completed');
            $this->assertSame([$action], $response->json('task.payload.actions'));
        } else {
            $service->execute($task);
        }
        $task->refresh();
        $this->assertIsArray($task->payload);
        $this->assertSame('completed', $task->payload['step_results'][0]['status']);
        $this->assertArrayNotHasKey('message', $task->payload['step_results'][0]);
        if ($path !== 'single') {
            $this->assertSame([$action], $task->payload['actions']);
        }
        $this->assertStringNotContainsString($binary, (string) $task->getRawOriginal('payload'));
        json_encode($task->payload, JSON_THROW_ON_ERROR);
        $this->assertSame(1, ScheduledTask::count());
    }

    public function test_binary_and_legacy_transport_envelopes_are_not_human_messages(): void
    {
        foreach (["\x00\x03valid-ascii", "\xff\x80", '{"errorCode":0,"data":"response"}', '_amf_response: success'] as $transport) {
            $outcome = StepOutcome::completed(0, 'send_specialist', $transport);
            $this->assertNull($outcome->message);
            $this->assertArrayNotHasKey('message', $outcome->toArray());
            json_encode($outcome->toArray(), JSON_THROW_ON_ERROR);
        }
        $outcome = StepOutcome::completed(0, 'produce_buff', 'Рукопись поставлена в очередь');
        $this->assertSame('Рукопись поставлена в очередь', $outcome->message);
    }

    public function test_manual_corrupt_sequence_returns_actionable_422_without_authentication(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $this->app->instance(TsoAuthService::class, $auth);
        $account = Account::create(['username' => 'corrupt_fixture', 'password' => 'fixture-only', 'region' => 'ru']);
        $task = ScheduledTask::create(['account_id' => $account->id, 'task_type' => TaskType::Sequence,
            'payload' => [], 'schedule_type' => ScheduleType::Daily, 'status' => TaskStatus::Pending]);
        DB::table('scheduled_tasks')->where('id', $task->id)->update(['payload' => '0']);
        $task->refresh();
        try {
            app(TaskExecutionService::class)->executeSingleSequenceAction($task, 0);
            $this->fail('Corrupt definition must be rejected before authentication.');
        } catch (TaskExecutionException $e) {
            $this->assertSame(422, $e->httpStatus());
            $this->assertSame('error.payload_invalid', $e->getTranslationKey());
        }
        $persisted = $task->fresh();
        $this->assertNotNull($persisted);
        $this->assertSame(0, $persisted->payload);
        $this->actingAs(User::factory()->create())
            ->postJson("/api/tasks/{$task->id}/actions/0/execute")
            ->assertStatus(422);
    }

    public function test_summary_truncation_preserves_utf8_at_byte_boundary(): void
    {
        config(['game.tasks.max_result_length' => 12]);
        $text = str_repeat('Я', 30);
        foreach ([TaskResultSummary::formatSingle($text), TaskResultSummary::formatSequence($text, false, true)] as $summary) {
            $this->assertSame(1, preg_match('//u', $summary));
            json_encode(['summary' => $summary], JSON_THROW_ON_ERROR);
        }
    }
}
