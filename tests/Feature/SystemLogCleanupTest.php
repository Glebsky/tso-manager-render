<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemLogCleanupService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SystemLogCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $authMock = Mockery::mock(TsoAuthService::class);
        $amfMock = Mockery::mock(TsoAmfService::class);
        $this->app->instance(TsoAuthService::class, $authMock);
        $this->app->instance(TsoAmfService::class, $amfMock);

        Setting::set('sync_interval', 0);

        $this->account = Account::create([
            'username' => 'test_log_user',
            'email' => 'test_log_user@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    public function test_scheduler_prunes_expired_logs_when_due_and_updates_last_cleanup_timestamp(): void
    {
        Setting::set('log_retention_days', 30);
        Setting::set('last_log_cleanup_at', null);

        // Old log (40 days ago) - should be deleted
        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'info',
            'message' => 'Old log message',
            'created_at' => Carbon::now()->subDays(40),
        ]);

        // Recent log (5 days ago) - should be kept
        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'info',
            'message' => 'Recent log message',
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertEquals(2, BotLog::count());

        Artisan::call('tso:run-scheduler');

        $this->assertEquals(1, BotLog::count());
        $this->assertDatabaseMissing('bot_logs', ['message' => 'Old log message']);
        $this->assertDatabaseHas('bot_logs', ['message' => 'Recent log message']);
        $this->assertNotNull(Setting::get('last_log_cleanup_at'));
    }

    public function test_scheduler_skips_cleanup_when_retention_period_is_zero(): void
    {
        Setting::set('log_retention_days', 0);
        Setting::set('last_log_cleanup_at', null);

        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'info',
            'message' => 'Very old log',
            'created_at' => Carbon::now()->subDays(100),
        ]);

        Artisan::call('tso:run-scheduler');

        $this->assertEquals(1, BotLog::count());
        $this->assertNull(Setting::get('last_log_cleanup_at'));
    }

    public function test_scheduler_skips_cleanup_when_interval_has_not_elapsed(): void
    {
        Setting::set('log_retention_days', 30);
        Setting::set('last_log_cleanup_at', Carbon::now()->subHours(2)->toIso8601String());

        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'info',
            'message' => 'Old log',
            'created_at' => Carbon::now()->subDays(50),
        ]);

        Artisan::call('tso:run-scheduler');

        // Since only 2 hours passed since last cleanup, cleanup should be skipped
        $this->assertEquals(1, BotLog::count());
    }

    public function test_scheduler_continues_when_log_cleanup_fails(): void
    {
        Setting::set('log_retention_days', 30);
        Setting::set('last_log_cleanup_at', null);

        $mockService = Mockery::mock(SystemLogCleanupService::class)->makePartial();
        $mockService->shouldReceive('shouldRunCleanup')->andReturn(true);
        $mockService->shouldReceive('cleanExpiredLogs')->andThrow(new \RuntimeException('DB connection failed'));

        $this->app->instance(SystemLogCleanupService::class, $mockService);

        Log::spy();

        $exitCode = Artisan::call('tso:run-scheduler');

        $this->assertEquals(0, $exitCode);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Retention cleanup failed: DB connection failed');
            });
    }

    public function test_clear_logs_endpoint_truncates_all_bot_logs(): void
    {
        $user = User::factory()->create();

        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'info',
            'message' => 'Log 1',
            'created_at' => Carbon::now(),
        ]);
        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'error',
            'message' => 'Log 2',
            'created_at' => Carbon::now(),
        ]);

        $this->assertEquals(2, BotLog::count());

        $response = $this->actingAs($user)->deleteJson('/api/settings/logs');

        $response->assertNoContent();

        $this->assertEquals(0, BotLog::count());
    }
}
