<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\SessionBusyException;
use App\Models\Account;
use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
use App\Services\Tasks\Execution\ActionRetryPolicy;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

/**
 * FR-008 / INV-004 — the per-account game-session lock is never bypassed (WP-07).
 */
class SessionLockTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Fail fast: nothing in these tests should ever wait 20 s for a lock.
        config(['game.session_lock_wait_seconds' => 1]);

        // An unsupported URL scheme makes resolveServerUrl() throw a very
        // specific RuntimeException — if that ever surfaces, the transport was
        // reached despite the busy lock.
        $this->account = Account::create([
            'username' => 'lock_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'lock_user',
            'dso_auth_user' => '3001',
            'dso_auth_token' => 'token_lock',
            'bb_url' => 'ftp://would-be-transport-call.invalid/',
        ]);
    }

    public function test_send_command_throws_session_busy_and_does_not_touch_transport_when_lock_is_held(): void
    {
        $foreign = Cache::lock("tso:amf_lock:{$this->account->id}", 60);
        $this->assertTrue($foreign->get(), 'precondition: another process holds the lock');

        $client = $this->app->make(HttpTsoClient::class);

        try {
            $client->sendCommand($this->account, new \stdClass);
            $this->fail('Expected SessionBusyException');
        } catch (SessionBusyException $e) {
            $this->assertStringContainsString("account #{$this->account->id}", $e->getMessage());
            $this->assertStringContainsString('amf lock', $e->getMessage());
        } finally {
            $foreign->release();
        }

        // The foreign lock is still owned by the other process: sendCommand
        // did not release or steal it.
        $this->assertTrue(Cache::lock("tso:amf_lock:{$this->account->id}", 60)->get(), 'lock is free again after the foreign release only');
    }

    public function test_send_command_releases_the_lock_when_transport_fails(): void
    {
        $client = $this->app->make(HttpTsoClient::class);

        try {
            $client->sendCommand($this->account, new \stdClass);
            $this->fail('Expected transport RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(SessionBusyException::class, $e);
            $this->assertStringContainsString('Invalid or unsupported URL scheme', $e->getMessage());
        }

        $this->assertTrue(Cache::lock("tso:amf_lock:{$this->account->id}", 60)->get(), 'lock must be released in finally');
    }

    public function test_lock_ttl_covers_resolve_plus_command(): void
    {
        config(['game.http_timeout' => 30]);

        // Worst case under the lock: authenticate + Z + command, each bounded by http_timeout.
        $ttl = (int) config('game.session_lock_ttl_seconds');

        $this->assertGreaterThanOrEqual(3 * 30 + 10, $ttl);
    }

    public function test_login_throws_session_busy_and_does_not_start_when_lock_is_held(): void
    {
        // A cooldown makes performLogin() throw a RuntimeException with this
        // message — if we see it, the login proceeded without the lock.
        Cache::put("account_login_cooldown:{$this->account->id}", 'captcha sentinel', 60);

        $foreign = Cache::lock("tso:login_lock:{$this->account->id}", 60);
        $this->assertTrue($foreign->get());

        try {
            $this->app->make(TsoAuthService::class)->login($this->account);
            $this->fail('Expected SessionBusyException');
        } catch (SessionBusyException $e) {
            $this->assertStringContainsString('login lock', $e->getMessage());
        } finally {
            $foreign->release();
        }
    }

    public function test_retry_policy_waits_and_retries_session_busy_without_relogin(): void
    {
        Sleep::fake();
        config(['game.tasks.max_action_attempts' => 3, 'game.tasks.session_busy_retry_delay' => 5]);

        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $auth->shouldNotReceive('resetSession');

        $amf = Mockery::mock(TsoAmfService::class);
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldNotReceive('resetClient');

        $policy = new ActionRetryPolicy(new GameSessionGuard(new GameSessionRecovery($auth, $amf, app(ZoneParserService::class), new VersionedZoneCacheInvalidator(Cache::store('array')), new GameClientIdentity)), new VersionedZoneCacheInvalidator(Cache::store('array')));

        $calls = 0;
        $result = $policy->execute($this->account, 'stop_production', function () use (&$calls): string {
            $calls++;
            if ($calls < 3) {
                throw SessionBusyException::forAccount(1, 'amf', 1);
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(3, $calls);
        Sleep::assertSleptTimes(2);
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(5)->seconds()]);
    }

    public function test_retry_policy_gives_up_after_max_attempts_when_session_stays_busy(): void
    {
        Sleep::fake();
        config(['game.tasks.max_action_attempts' => 2]);

        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $amf = Mockery::mock(TsoAmfService::class);

        $policy = new ActionRetryPolicy(new GameSessionGuard(new GameSessionRecovery($auth, $amf, app(ZoneParserService::class), new VersionedZoneCacheInvalidator(Cache::store('array')), new GameClientIdentity)), new VersionedZoneCacheInvalidator(Cache::store('array')));

        $this->expectException(SessionBusyException::class);

        $policy->execute($this->account, 'stop_production', function (): string {
            throw SessionBusyException::forAccount(1, 'amf', 1);
        });
    }
}
