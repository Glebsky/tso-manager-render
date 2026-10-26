<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\Tasks\Execution\SequenceRunner;
use App\Services\Tasks\Execution\TaskPayload;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\TsoAuthService;
use Illuminate\Database\PostgresConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TaskPayloadIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create(['username' => 'payload_integrity', 'password' => 'fixture-only', 'region' => 'ru']);
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $auth->shouldNotReceive('resetSession');
        $this->app->instance(TsoAuthService::class, $auth);
    }

    /** @return array<string, array{string}> */
    public static function transitions(): array
    {
        return ['reserve' => ['reserve'], 'start' => ['start'], 'progress' => ['progress'], 'finish' => ['finish']];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        // Existing associative actions are valid and must not be discarded.
        return ['actions' => ['first' => ['task_type' => 'collect_pickups', 'payload' => []]], 'label' => 'unicode \u{0410}'];
    }

    private function task(string $transition = 'reserve'): ScheduledTask
    {
        return ScheduledTask::create([
            'account_id' => $this->account->id,
            'name' => 'Payload integrity fixture',
            'task_type' => TaskType::Sequence,
            'schedule_type' => ScheduleType::Daily,
            'run_at_time' => '12:00',
            'payload' => $this->payload(),
            'status' => match ($transition) {
                'reserve' => TaskStatus::Pending,
                'start' => TaskStatus::Queued,
                default => TaskStatus::Running,
            },
            'execution_token' => $transition === 'reserve' ? null : 'integrity-token',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function transition(string $transition, ScheduledTask $task, array $payload): bool
    {
        $machine = app(TaskStateMachine::class);

        return match ($transition) {
            'reserve' => $machine->reserve($task, 'integrity-token', $payload),
            'start' => $machine->start($task, 'integrity-token', $payload),
            'progress' => $machine->progress($task, 'integrity-token', 1, $payload),
            'finish' => $machine->finish($task, 'integrity-token', TaskStatus::Completed, 'OK: fixture', $payload),
            default => throw new InvalidArgumentException('Unknown fixture transition'),
        };
    }

    #[DataProvider('transitions')]
    public function test_bulk_update_encodes_payload_exactly_once_and_keeps_actions(string $transition): void
    {
        $task = $this->task($transition);
        $payload = $this->payload();
        $payload['step_results'] = [['status' => 'completed', 'message' => 'safe text']];

        $this->assertTrue($this->transition($transition, $task, $payload));
        if ($transition === 'reserve') {
            unset($payload['step_results']);
        }

        $raw = DB::table('scheduled_tasks')->where('id', $task->id)->value('payload');
        $this->assertIsString($raw);
        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertSame($payload, json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($payload, $task->fresh()?->payload);
    }

    #[DataProvider('transitions')]
    public function test_invalid_utf8_is_atomic_and_leaves_original_payload_and_state_untouched(string $transition): void
    {
        $task = $this->task($transition);
        $before = DB::table('scheduled_tasks')->where('id', $task->id)->first();
        $payload = $this->payload();
        $payload['step_results'] = [['message' => "legacy AMF \xB1\xFF"]];
        // Reserve deliberately clears stale results, so corrupt an action too.
        $payload['actions']['first']['payload'] = ['raw' => "\xB1\xFF"];

        try {
            $this->transition($transition, $task, $payload);
            $this->fail('Invalid UTF-8 must throw before any update.');
        } catch (JsonException) {
            $this->assertEquals($before, DB::table('scheduled_tasks')->where('id', $task->id)->first());
            $this->assertSame($this->payload(), $task->fresh()?->payload);
        }
    }

    /** @return array<string, array{mixed}> */
    public static function malformedPayloads(): array
    {
        return [
            'integer' => [0], 'string' => ['broken'], 'null' => [null], 'list' => [[['task_type' => 'collect_pickups']]],
            'missing-actions' => [[]], 'empty-actions' => [['actions' => []]],
            'scalar-actions' => [['actions' => 1]], 'scalar-step' => [['actions' => ['not an action']]],
            'missing-type' => [['actions' => [['payload' => []]]]],
            'unknown-type' => [['actions' => [['task_type' => 'unknown']]]],
            'nested-sequence' => [['actions' => [['task_type' => 'sequence']]]],
            'scalar-action-payload' => [['actions' => [['task_type' => 'collect_pickups', 'payload' => 0]]]],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function test_malformed_sequence_rejects_before_auth_or_commands_without_empty_success(mixed $payload): void
    {
        $task = $this->task('start');
        DB::table('scheduled_tasks')->where('id', $task->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $before = DB::table('scheduled_tasks')->where('id', $task->id)->first();

        try {
            app(SequenceRunner::class)->run($task, $this->account, 'integrity-token');
            $this->fail('Malformed sequence must never finish successfully.');
        } catch (TaskExecutionException $exception) {
            $this->assertSame('error.payload_invalid', $exception->getTranslationKey());
            $this->assertSame(422, $exception->httpStatus());
            $this->assertEquals($before, DB::table('scheduled_tasks')->where('id', $task->id)->first());
        }
    }

    public function test_scalar_task_cannot_be_reserved(): void
    {
        $task = $this->task();
        DB::table('scheduled_tasks')->where('id', $task->id)->update(['payload' => '0']);
        $task->refresh();
        $before = DB::table('scheduled_tasks')->where('id', $task->id)->first();

        try {
            app(TaskStateMachine::class)->reserve($task, 'integrity-token');
            $this->fail('A scalar payload must not be reserved.');
        } catch (TaskExecutionException $exception) {
            $this->assertSame('error.payload_invalid', $exception->getTranslationKey());
            $this->assertSame(422, $exception->httpStatus());
            $this->assertEquals($before, DB::table('scheduled_tasks')->where('id', $task->id)->first());
        }
    }

    public function test_pg_and_sqlite_grammars_bind_the_same_single_encoded_object_without_connecting_to_pg(): void
    {
        $encoded = TaskPayload::encode($this->payload());
        $pg = new PostgresConnection(static fn () => throw new \LogicException('PostgreSQL access forbidden'));

        foreach ([DB::connection(), $pg] as $connection) {
            $query = $connection->table('scheduled_tasks')->where('id', 123);
            $grammar = $connection->getQueryGrammar();
            $sql = $grammar->compileUpdate($query, ['payload' => $encoded]);
            $bindings = $grammar->prepareBindingsForUpdate($query->getRawBindings(), ['payload' => $encoded]);
            $this->assertStringContainsString('"payload" = ?', $sql);
            $this->assertSame([$encoded, 123], $bindings);
            $this->assertInstanceOf(\stdClass::class, json_decode($bindings[0], false, 512, JSON_THROW_ON_ERROR));
        }

        $this->assertSame('{}', TaskPayload::encode([]));
    }
}
