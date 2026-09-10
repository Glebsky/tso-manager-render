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
use Mockery\MockInterface;
use Tests\TestCase;

class AccountSyncTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $authMock;

    private MockInterface $amfMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
    }

    public function test_scheduler_dispatches_account_sync_job_when_due(): void
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

        Queue::assertPushed(AccountSyncJob::class, function ($job) use ($account) {
            return $job->account->id === $account->id;
        });
    }

    public function test_scheduler_skips_account_sync_when_not_due(): void
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

    public function test_scheduler_skips_account_sync_when_interval_is_zero(): void
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

    public function test_scheduler_atomic_lock_prevents_duplicate_account_sync(): void
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
        Cache::add("account_sync_lock:{$account->id}", true, 300);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertNotPushed(AccountSyncJob::class);
    }

    public function test_account_sync_job_executes_successfully_and_releases_lock(): void
    {
        // No storage fake needed

        $account = Account::create([
            'username' => 'sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_user',
        ]);

        // Simulate lock acquired by scheduler
        $this->assertTrue(Cache::add("account_sync_lock:{$account->id}", true, 300));

        // Mock dependencies
        $this->authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
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
        $this->assertIsArray($account->zone_data);

        // Lock must be released, so we can acquire it again
        $this->assertTrue(Cache::add("account_sync_lock:{$account->id}", true, 300));
    }

    public function test_zone_data_is_hidden_by_default_in_json_and_visible_when_explicit(): void
    {
        $account = Account::create([
            'username' => 'test_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'test_user',
            'zone_data' => json_encode([
                'avatarId' => 5,
                'gameWorldName' => 'Tutum',
                'buildings' => [['buildingName' => 'House']],
            ]),
        ]);

        $array = $account->toArray();
        $this->assertArrayNotHasKey('zone_data', $array);
        $this->assertEquals(5, $array['avatar_id']);
        $this->assertEquals(1, $array['building_count']);
        $this->assertEquals('Tutum', $array['server_name']);

        $account->makeVisible('zone_data');
        $visibleArray = $account->toArray();
        $this->assertArrayHasKey('zone_data', $visibleArray);

        $response = $this->getJson('/api/dashboard');
        $response->assertStatus(200);
        $response->assertJsonMissingPath('accounts.0.zone_data');
        $response->assertJsonPath('accounts.0.avatar_id', 5);
        $response->assertJsonPath('accounts.0.building_count', 1);
        $response->assertJsonPath('accounts.0.server_name', 'Tutum');
    }

    public function test_scheduler_skips_account_sync_when_session_expired_or_login_cooldown_active(): void
    {
        Queue::fake();

        Setting::set('sync_interval', 15);
        Setting::set('log_retention_days', 30);
        Setting::set('timezone', 'UTC');

        $account = Account::create([
            'username' => 'captcha_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'captcha_user',
            'status' => 'session_expired',
            'last_sync_at' => now()->subMinutes(60),
        ]);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        Queue::assertNotPushed(AccountSyncJob::class);
    }

    public function test_account_sync_recovers_from_error_1012_with_relogin_and_succeeds(): void
    {
        $account = Account::create([
            'username' => 'sync_1012_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'sync_1012_user',
        ]);

        $this->authMock->shouldReceive('resetSession')->once()->with(Mockery::any());
        $this->authMock->shouldReceive('ensureAuthenticated')->twice()->andReturnNull();

        $this->amfMock->shouldReceive('getZone')->twice()->andReturn('zone-amf-bytes');
        $this->amfMock->shouldReceive('invalidateSession')->once()->with((int) $account->id);
        $this->amfMock->shouldReceive('resetClient')->once()->with((int) $account->id);

        $parserMock = Mockery::mock(ZoneParserService::class);
        $parserMock->shouldReceive('parse')
            ->with('zone-amf-bytes')
            ->twice()
            ->andReturnValues([
                ['errorCode' => 1012, 'buildings' => []],
                [
                    'errorCode' => 0,
                    'buildings' => [['buildingName' => 'Mayor\'s House', 'grid' => 100]],
                    'userID' => 12345,
                    'level' => 30,
                    'gameWorldName' => 'TestWorld',
                ],
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
        $this->assertIsArray($account->zone_data);
    }

    public function test_sync_preserves_existing_friends_when_get_friend_list_throws_exception(): void
    {
        $existingFriends = [
            ['id' => 8888, 'username' => 'KeepMe', 'nickname' => 'KeepMe', 'playerLevel' => 50, 'level' => 50, 'avatarId' => 2, 'friendSince' => null],
        ];

        $account = Account::create([
            'username' => 'test_user_friends_ex',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'test_user',
            'zone_data' => json_encode([
                'friends' => $existingFriends,
            ]),
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
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

        $this->amfMock->shouldReceive('getFriendList')->once()->andThrow(new \Exception('Network timeout'));

        $job = new AccountSyncJob($account);
        $job->handle($this->app->make(AccountSyncService::class));

        $account->refresh();
        $this->assertEquals('online', $account->status);
        $this->assertIsArray($account->zone_data);
        $this->assertSame(8888, $account->zone_data['friends'][0]['id'] ?? null);
    }

    public function test_sync_preserves_existing_friends_when_get_friend_list_returns_error_code(): void
    {
        $existingFriends = [
            ['id' => 7777, 'username' => 'KeepMe2', 'nickname' => 'KeepMe2', 'playerLevel' => 40, 'level' => 40, 'avatarId' => 3, 'friendSince' => null],
        ];

        $account = Account::create([
            'username' => 'test_user_friends_err',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'test_user',
            'zone_data' => json_encode([
                'friends' => $existingFriends,
            ]),
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
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

        $this->amfMock->shouldReceive('getFriendList')->once()->andReturn('friends-err-amf');
        $parserMock->shouldReceive('parse')
            ->with('friends-err-amf')
            ->once()
            ->andReturn([
                'errorCode' => 1005, // Session expired error
                'friends' => [],
            ]);

        $job = new AccountSyncJob($account);
        $job->handle($this->app->make(AccountSyncService::class));

        $account->refresh();
        $this->assertEquals('online', $account->status);
        $this->assertIsArray($account->zone_data);
        $this->assertSame(7777, $account->zone_data['friends'][0]['id'] ?? null);
    }

    public function test_sync_clears_friends_when_get_friend_list_succeeds_with_zero_friends(): void
    {
        $existingFriends = [
            ['id' => 6666, 'username' => 'OldFriend', 'nickname' => 'OldFriend', 'playerLevel' => 15, 'level' => 15, 'avatarId' => 1, 'friendSince' => null],
        ];

        $account = Account::create([
            'username' => 'test_user_friends_empty',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'test_user',
            'zone_data' => json_encode([
                'friends' => $existingFriends,
            ]),
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
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

        $this->amfMock->shouldReceive('getFriendList')->once()->andReturn('friends-empty-amf');
        $parserMock->shouldReceive('parse')
            ->with('friends-empty-amf')
            ->once()
            ->andReturn([
                'errorCode' => 0,
                'friends' => [],
            ]);

        $job = new AccountSyncJob($account);
        $job->handle($this->app->make(AccountSyncService::class));

        $account->refresh();
        $this->assertEquals('online', $account->status);
        $this->assertIsArray($account->zone_data);
        $this->assertSame([], $account->zone_data['friends']);
    }

    public function test_sync_rethrows_captcha_error_when_relogin_encounters_captcha_on_error_1005(): void
    {
        $account = Account::create([
            'username' => 'test_user_captcha',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'test_user',
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->once()->andReturnNull();
        $this->amfMock->shouldReceive('getZone')->once()->andReturn('zone-amf-bytes');

        $parserMock = Mockery::mock(ZoneParserService::class);
        $parserMock->shouldReceive('parse')
            ->with('zone-amf-bytes')
            ->once()
            ->andReturn([
                'errorCode' => 1005,
            ]);
        $this->app->instance(ZoneParserService::class, $parserMock);

        $this->authMock->shouldReceive('resetSession')->once()->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id));
        $this->amfMock->shouldReceive('invalidateSession')->once()->with((int) $account->id);
        $this->amfMock->shouldReceive('resetClient')->once()->with((int) $account->id);
        $this->authMock->shouldReceive('ensureAuthenticated')->once()->andThrow(new \RuntimeException('Ubisoft requires CAPTCHA verification. Please update the session manually in account settings.'));
        $this->authMock->shouldReceive('isCaptchaOr2faError')->andReturn(true);

        $job = new AccountSyncJob($account);
        $job->handle($this->app->make(AccountSyncService::class));

        $account->refresh();
        $this->assertEquals('session_expired', $account->status);
    }

    public function test_sync_recovers_from_initial_auth_failure_by_resetting_session_and_retrying(): void
    {
        $account = Account::create([
            'username' => 'recover_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'recover_user',
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id))
            ->andThrow(new \RuntimeException('Login failed: Temporary session error'));

        $this->authMock->shouldReceive('isCaptchaOr2faError')
            ->with('Login failed: Temporary session error')
            ->andReturn(false);

        $this->authMock->shouldReceive('resetSession')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id));
        $this->amfMock->shouldReceive('invalidateSession')
            ->once()
            ->with((int) $account->id);
        $this->amfMock->shouldReceive('resetClient')
            ->once()
            ->with((int) $account->id);

        $this->authMock->shouldReceive('ensureAuthenticated')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id))
            ->andReturnNull();

        $this->amfMock->shouldReceive('getZone')->once()->andReturn('zone-amf-bytes');

        $parserMock = Mockery::mock(ZoneParserService::class);
        $parserMock->shouldReceive('parse')
            ->with('zone-amf-bytes')
            ->once()
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [['buildingName' => "Mayor's House", 'grid' => 100]],
                'userID' => 12345,
                'level' => 30,
                'gameWorldName' => 'TestWorld',
            ]);
        $this->app->instance(ZoneParserService::class, $parserMock);

        $this->amfMock->shouldReceive('getFriendList')->once()->andReturn('friends-bytes');
        $parserMock->shouldReceive('parse')->with('friends-bytes')->once()->andReturn(['friends' => []]);

        $syncService = $this->app->make(AccountSyncService::class);
        $result = $syncService->sync($account);

        $this->assertSame(12345, $result['userID']);
        $account->refresh();
        $this->assertSame('online', $account->status);
    }

    public function test_controller_manual_sync_clears_login_cooldown(): void
    {
        $account = Account::create([
            'username' => 'manual_sync_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'manual_sync_user',
        ]);

        Cache::put("account_login_cooldown:{$account->id}", 'Captcha required', 900);
        $this->assertTrue(Cache::has("account_login_cooldown:{$account->id}"));

        $this->authMock->shouldReceive('clearCooldown')->once()->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id))->andReturnUsing(function ($acc) {
            Cache::forget("account_login_cooldown:{$acc->id}");
        });
        $this->authMock->shouldReceive('ensureAuthenticated')->once()->andReturnNull();
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

        $this->amfMock->shouldReceive('getFriendList')->once()->andReturn('friends-bytes');
        $parserMock->shouldReceive('parse')->with('friends-bytes')->once()->andReturn(['friends' => []]);

        $response = $this->postJson("/api/accounts/{$account->id}/sync");
        $response->assertOk();

        $this->assertFalse(Cache::has("account_login_cooldown:{$account->id}"));
    }

    public function test_update_session_allows_updating_password_and_tokens(): void
    {
        $account = Account::create([
            'username' => 'session_update_user',
            'password' => 'old_secret',
            'region' => 'ru',
            'nickname' => 'session_update_user',
        ]);

        $this->authMock->shouldReceive('getCookieFile')->once()->andReturn(sys_get_temp_dir().'/cookie_test.txt');
        $this->authMock->shouldReceive('resetSession')->once()->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id));
        $this->authMock->shouldReceive('markSessionVerified')->once();
        $this->amfMock->shouldReceive('invalidateSession')->once()->with($account->id);
        $this->amfMock->shouldReceive('resetClient')->once();

        $response = $this->putJson("/api/accounts/{$account->id}/session", [
            'dso_auth_token' => 'new_dso_token',
            'dso_auth_user' => 'new_dso_user',
            'bb_url' => 'https://r01-ls.thesettlersonline.ru/',
            'password' => 'new_secret_password',
        ]);

        $response->assertOk();
        $account->refresh();
        $this->assertEquals('new_dso_token', $account->dso_auth_token);
        $this->assertEquals('new_dso_user', $account->dso_auth_user);
        $this->assertEquals('new_secret_password', $account->password);
    }

    public function test_update_session_allows_updating_only_password(): void
    {
        $account = Account::create([
            'username' => 'password_only_user',
            'password' => 'initial_password',
            'region' => 'ru',
            'nickname' => 'password_only_user',
        ]);

        $this->authMock->shouldReceive('getCookieFile')->once()->andReturn(sys_get_temp_dir().'/cookie_test.txt');
        $this->authMock->shouldReceive('resetSession')->once()->with(Mockery::on(fn ($arg) => $arg instanceof Account && $arg->id === $account->id));
        $this->amfMock->shouldReceive('invalidateSession')->once()->with($account->id);
        $this->amfMock->shouldReceive('resetClient')->once();

        $response = $this->putJson("/api/accounts/{$account->id}/session", [
            'password' => 'brand_new_password',
        ]);

        $response->assertOk();
        $account->refresh();
        $this->assertEquals('brand_new_password', $account->password);
    }
}
