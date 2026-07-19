<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AccountSyncJob;
use App\Models\Account;
use App\Models\Setting;
use App\Services\AccountSyncService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AccountSyncTest extends TestCase
{
    use RefreshDatabase;

    private $authMock;

    private $amfMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
    }

    public function test_scheduler_dispatches_account_sync_job_when_due()
    {
        Queue::fake();

        Setting::set('sync_interval', 15);
        Setting::set('log_retention_days', 30);
        Setting::set('timezone', 'UTC');

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
            'last_sync_at' => now()->subMinutes(20),
        ]);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertPushedOn('tso-tasks', AccountSyncJob::class, function ($job) use ($account) {
            return $job->account->id === $account->id;
        });
    }

    public function test_scheduler_skips_account_sync_when_not_due()
    {
        Queue::fake();

        Setting::set('sync_interval', 15);
        Setting::set('log_retention_days', 30);
        Setting::set('timezone', 'UTC');

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
            'last_sync_at' => now()->subMinutes(10), // only 10 minutes elapsed, interval is 15
        ]);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertNotPushed(AccountSyncJob::class);
    }

    public function test_scheduler_skips_account_sync_when_interval_is_zero()
    {
        Queue::fake();

        Setting::set('sync_interval', 0);
        Setting::set('log_retention_days', 30);
        Setting::set('timezone', 'UTC');

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
            'last_sync_at' => now()->subMinutes(60),
        ]);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertNotPushed(AccountSyncJob::class);
    }

    public function test_scheduler_atomic_lock_prevents_duplicate_account_sync()
    {
        Queue::fake();

        Setting::set('sync_interval', 15);
        Setting::set('log_retention_days', 30);
        Setting::set('timezone', 'UTC');

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
            'last_sync_at' => now()->subMinutes(20),
        ]);

        // Pre-acquire lock
        Cache::lock("account_sync_lock:{$account->id}", 300)->get();

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertNotPushed(AccountSyncJob::class);
    }

    public function test_account_sync_job_executes_successfully_and_releases_lock()
    {
        // No storage fake needed

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
        ]);

        // Simulate lock acquired by scheduler
        $lock = Cache::lock("account_sync_lock:{$account->id}", 300);
        $this->assertTrue($lock->get());

        // Mock dependencies
        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);
        $this->amfMock->shouldReceive('getZone')->once()->andReturn('zone-amf-bytes');

        $parserMock = Mockery::mock(ZoneParserService::class);
        $parserMock->shouldReceive('parse')
            ->with('zone-amf-bytes')
            ->once()
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [['buildingName' => 'Mayor\'s House', 'grid' => 100]],
                'userID' => 12345,
                'level' => 30,
                'gameWorldName' => 'TestWorld',
            ]);
        $this->app->instance(ZoneParserService::class, $parserMock);

        $this->amfMock->shouldReceive('getFriendList')->once()->andReturn('friends-amf-bytes');
        $parserMock->shouldReceive('parse')
            ->with('friends-amf-bytes')
            ->once()
            ->andReturn([
                'friends' => [
                    ['id' => 9999, 'nickname' => 'Friend1', 'playerLevel' => 10],
                ],
            ]);

        $job = new AccountSyncJob($account);
        $job->handle($this->app->make(AccountSyncService::class));

        $account->refresh();
        $this->assertEquals('online', $account->status);
        $this->assertNotNull($account->last_sync_at);
        $this->assertJson($account->zone_data);

        // Lock must be released, so we can acquire it again
        $this->assertTrue($lock->get());
    }
}
