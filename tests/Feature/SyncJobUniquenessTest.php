<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccountSyncException;
use App\Exceptions\AuthChallengeException;
use App\Exceptions\SessionExpiredException;
use App\Exceptions\UnrecoverableAuthError;
use App\Jobs\AccountSyncJob;
use App\Jobs\MarketSyncJob;
use App\Models\Account;
use App\Models\MarketServerConnection;
use App\Models\Setting;
use App\Services\Account\Sync\AccountSyncPersister;
use App\Services\AccountSyncService;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * A-13 / WP-10 — sync jobs are deduplicated by ShouldBeUnique alone, the
 * unique window covers the retry budget, and "unrecoverable" is decided by
 * exception type.
 */
class SyncJobUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create([
            'username' => 'unique_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'unique_user',
            'last_sync_at' => now()->subMinutes(60),
        ]);
    }

    public function test_sync_jobs_are_unique_for_longer_than_their_retry_window(): void
    {
        foreach ([AccountSyncJob::class, MarketSyncJob::class] as $jobClass) {
            $reflection = new ReflectionClass($jobClass);
            $this->assertContains(ShouldBeUnique::class, $reflection->getInterfaceNames(), "{$jobClass} must be ShouldBeUnique");

            $defaults = $reflection->getDefaultProperties();
            $retryWindow = $defaults['tries'] * $defaults['timeout'] + array_sum($defaults['backoff']);

            $this->assertGreaterThanOrEqual($retryWindow, $defaults['uniqueFor'], "{$jobClass}::\$uniqueFor must cover tries×timeout + backoffs ({$retryWindow}s)");
        }
    }

    public function test_scheduler_does_not_queue_a_second_account_sync_while_one_is_pending(): void
    {
        Queue::fake();
        Setting::set('sync_interval', 15);

        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);
        Artisan::call('tso:run-scheduler', ['--mode' => 'queue']);

        // The unique lock taken by the first dispatch makes the second a no-op;
        // no hand-rolled cache key is involved anymore.
        Queue::assertPushed(AccountSyncJob::class, 1);
        $this->assertFalse(Cache::has("account_sync_lock:{$this->account->id}"));
    }

    public function test_scheduler_does_not_queue_a_second_market_sync_for_the_same_server(): void
    {
        Queue::fake();
        Setting::set('market_sync_interval', '15');
        MarketServerConnection::create([
            'server_id' => 'ru_unique',
            'locale' => 'RU',
            'display_name' => 'RU',
            'account_id' => $this->account->id,
            'sync_status' => 'connected',
        ]);

        Artisan::call('tso:sync-market');
        Artisan::call('tso:sync-market');

        Queue::assertPushed(MarketSyncJob::class, 1);
        $this->assertFalse(Cache::has('market_sync_lock:server:ru_unique'));
    }

    public function test_market_sync_unique_id_is_the_server(): void
    {
        $this->assertSame('eu_1', (new MarketSyncJob($this->account, 'eu_1'))->uniqueId());
        $this->assertSame((string) $this->account->id, (new AccountSyncJob($this->account))->uniqueId());
    }

    public function test_unrecoverable_auth_error_is_decided_by_type_along_the_previous_chain(): void
    {
        $this->assertTrue(UnrecoverableAuthError::isUnrecoverable(AuthChallengeException::captcha()));
        $this->assertTrue(UnrecoverableAuthError::isUnrecoverable(AuthChallengeException::twoFactor()));
        $this->assertTrue(UnrecoverableAuthError::isUnrecoverable(new SessionExpiredException('rejected')));
        $this->assertTrue(UnrecoverableAuthError::isUnrecoverable(new AccountSyncException('1005', 401)));
        $this->assertTrue(UnrecoverableAuthError::isUnrecoverable(
            new AccountSyncException('Sync failed: wrapped', 500, [], AuthChallengeException::captcha())
        ));

        // Message text alone is not a signal (FR-009: no str_contains detection).
        $this->assertFalse(UnrecoverableAuthError::isUnrecoverable(new \RuntimeException('CAPTCHA required')));
        $this->assertFalse(UnrecoverableAuthError::isUnrecoverable(new \RuntimeException('Session expired')));
        $this->assertFalse(UnrecoverableAuthError::isUnrecoverable(new AccountSyncException('zone locked', 409)));
    }

    public function test_persister_marks_session_expired_only_for_typed_auth_errors(): void
    {
        $persister = new AccountSyncPersister;

        $persister->markError($this->account, new \RuntimeException('captcha in text only'));
        $this->assertSame('error', $this->account->refresh()->status);

        $persister->markError($this->account, AuthChallengeException::captcha());
        $this->assertSame('session_expired', $this->account->refresh()->status);
    }

    public function test_account_sync_job_fails_fast_on_auth_challenge_without_retrying(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once()->andThrow(AuthChallengeException::captcha());
        $auth->shouldReceive('isCaptchaOr2faError')->andReturn(true);
        $this->app->instance(TsoAuthService::class, $auth);

        // handle() must not rethrow: the job fails itself (fail()) instead of
        // letting the queue retry a CAPTCHA three times.
        (new AccountSyncJob($this->account))->handle($this->app->make(AccountSyncService::class));

        $this->assertSame('session_expired', $this->account->refresh()->status);
    }

    public function test_account_sync_job_rethrows_transient_errors_so_the_queue_retries(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->andThrow(new \RuntimeException('Network timeout'));
        $auth->shouldReceive('resetSession');
        $auth->shouldReceive('isCaptchaOr2faError')->andReturn(false);
        $this->app->instance(TsoAuthService::class, $auth);

        $amf = Mockery::mock(TsoAmfService::class);
        $amf->shouldReceive('invalidateSession');
        $amf->shouldReceive('resetClient');
        $this->app->instance(TsoAmfService::class, $amf);

        try {
            (new AccountSyncJob($this->account))->handle($this->app->make(AccountSyncService::class));
            $this->fail('A transient error must be rethrown for the queue to retry');
        } catch (AccountSyncException $e) {
            $this->assertStringContainsString('Network timeout', $e->getMessage());
        }

        $this->assertSame('error', $this->account->refresh()->status);
    }
}
