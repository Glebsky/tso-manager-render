<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NFR-002: the `next_run_at` migration is additive and backfills existing rows
 * from the fields the old scheduler evaluated.
 */
class NextRunAtBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_09_25_000001_add_next_run_at_to_scheduled_tasks.php';

    public function test_backfill_derives_next_run_at_for_legacy_rows(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');

        $account = Account::create([
            'username' => 'backfill_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'backfill_user',
        ]);

        $interval = ScheduledTask::create([
            'account_id' => $account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'interval', 'interval_minutes' => 15,
            'last_run_at' => Carbon::parse('2026-09-25 11:50:00'),
        ]);
        $daily = ScheduledTask::create([
            'account_id' => $account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'daily', 'run_at_time' => '14:00',
            'last_run_at' => Carbon::parse('2026-09-24 14:00:07'),
        ]);
        $once = ScheduledTask::create([
            'account_id' => $account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'once', 'run_at_datetime' => Carbon::parse('2026-09-26 10:00:00'),
        ]);
        $ranOnce = ScheduledTask::create([
            'account_id' => $account->id, 'task_type' => 'stop_production', 'payload' => ['grid' => 1],
            'schedule_type' => 'once', 'run_at_datetime' => Carbon::parse('2026-09-20 10:00:00'),
            'last_run_at' => Carbon::parse('2026-09-20 10:00:02'), 'is_active' => false,
        ]);

        // Simulate the pre-migration state: the column exists (RefreshDatabase ran
        // everything) but the rows have no slot yet.
        DB::table('scheduled_tasks')->update(['next_run_at' => null]);

        $migration = require base_path(self::MIGRATION);
        $reflection = new \ReflectionMethod($migration, 'backfill');
        $reflection->invoke($migration);

        $this->assertSame('2026-09-25 12:05:00', $interval->refresh()->next_run_at?->toDateTimeString());
        $this->assertSame('2026-09-25 14:00:00', $daily->refresh()->next_run_at?->toDateTimeString());
        $this->assertSame('2026-09-26 10:00:00', $once->refresh()->next_run_at?->toDateTimeString());
        $this->assertNull($ranOnce->refresh()->next_run_at, 'a once task that already ran has no slot');
    }
}
