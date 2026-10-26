<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR-004: cron mode drains the queues inline on every tick, queue mode relies
 * on a dedicated worker and must not start a second one.
 */
class SchedulerModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // queue:work is resolved lazily from the container: replace it with a
        // recorder so the test observes the invocation without draining queues.
        RecordingWorkCommand::$queues = [];
        $this->app->instance(WorkCommand::class, new RecordingWorkCommand);
    }

    public function test_cron_mode_runs_inline_worker_over_configured_queues(): void
    {
        Artisan::call('tso:run-scheduler', ['--mode' => 'cron']);

        $this->assertSame([implode(',', config('game.queues'))], RecordingWorkCommand::$queues);
    }

    public function test_queue_mode_does_not_run_inline_worker(): void
    {
        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        $this->assertSame([], RecordingWorkCommand::$queues);
    }

    public function test_explicit_work_flag_still_runs_inline_worker_in_queue_mode(): void
    {
        Artisan::call('tso:run-scheduler', ['--mode' => 'queue', '--work' => true]);

        $this->assertSame([implode(',', config('game.queues'))], RecordingWorkCommand::$queues);
    }

    public function test_inline_worker_respects_custom_queue_option(): void
    {
        Artisan::call('tso:run-scheduler', ['--mode' => 'cron', '--queue' => 'tso-tasks,tso-accounts']);

        $this->assertSame(['tso-tasks,tso-accounts'], RecordingWorkCommand::$queues);
    }

    public function test_inline_worker_respects_config_worker_queues(): void
    {
        config(['game.worker_queues' => 'tso-market']);

        Artisan::call('tso:run-scheduler', ['--mode' => 'cron']);

        $this->assertSame(['tso-market'], RecordingWorkCommand::$queues);
    }

    public function test_schedule_does_not_force_inline_worker(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->filter(fn (string $command) => str_contains($command, 'tso:run-scheduler'));

        $this->assertCount(1, $commands);
        $this->assertStringNotContainsString('--work', (string) $commands->first());
    }
}

/**
 * Test double for `queue:work`: records the requested queue list only.
 */
class RecordingWorkCommand extends Command
{
    protected $signature = 'queue:work {connection?} {--queue=} {--stop-when-empty} {--max-time=} {--max-jobs=} {--tries=}';

    /** @var list<mixed> */
    public static array $queues = [];

    public function handle(): int
    {
        self::$queues[] = $this->option('queue');

        return self::SUCCESS;
    }
}
