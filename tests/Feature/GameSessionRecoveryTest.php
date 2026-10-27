<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccountSyncException;
use App\Exceptions\AuthChallengeException;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Account\Sync\AccountSyncFetcher;
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
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class GameSessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->account = Account::create(['username' => 'recovery_fixture', 'password' => 'fixture', 'region' => 'ru']);
    }

    /** @return array{GameSessionRecovery, TsoAuthService&MockInterface, TsoAmfService&MockInterface, ZoneParserService&MockInterface, VersionedZoneCacheInvalidator} */
    private function dependencies(): array
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $amf = Mockery::mock(TsoAmfService::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, new GameClientIdentity);

        return [$recovery, $auth, $amf, $parser, $zones];
    }

    public function test_ordinary_success_does_not_reset_or_reauthenticate_or_enter_zone(): void
    {
        [$recovery, $auth, $amf, $parser, $zones] = $this->dependencies();
        $auth->shouldNotReceive('resetSession');
        $auth->shouldNotReceive('ensureAuthenticated');
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldNotReceive('forgetClientId');
        $amf->shouldNotReceive('getZone');
        $amf->shouldNotReceive('resetClient');
        $parser->shouldNotReceive('parse');
        $policy = new ActionRetryPolicy(new GameSessionGuard($recovery), $zones);
        $this->assertSame('ok', $policy->execute($this->account, 'collect_daily_login_reward', fn () => 'ok'));
    }

    public function test_forced_reauthentication_preserves_client_id_and_refreshes_account(): void
    {
        [$recovery, $auth, $amf] = $this->dependencies();
        $auth->shouldReceive('resetSession')->once()->globally()->ordered()->with($this->account);
        $amf->shouldReceive('invalidateSession')->once()->globally()->ordered()->with($this->account->id);
        $amf->shouldReceive('resetClient')->once()->globally()->ordered()->with($this->account->id);
        $amf->shouldNotReceive('forgetClientId');
        $auth->shouldReceive('ensureAuthenticated')->once()->globally()->ordered()->with($this->account, true)
            ->andReturnUsing(function (): void {
                Account::whereKey($this->account->id)->update(['nickname' => 'refreshed-fixture']);
            });
        $recovery->reauthenticate($this->account);
        $this->assertSame('refreshed-fixture', $this->account->nickname);
    }

    public function test_action_continues_only_after_reauthentication_and_successful_home_zone_entry(): void
    {
        [$recovery, $auth, $amf, $parser, $zones] = $this->dependencies();
        $auth->shouldReceive('resetSession')->once()->globally()->ordered();
        $amf->shouldReceive('invalidateSession')->once()->globally()->ordered();
        $amf->shouldReceive('resetClient')->once()->globally()->ordered();
        $auth->shouldReceive('ensureAuthenticated')->once()->globally()->ordered()->with($this->account, true);
        $amf->shouldNotReceive('forgetClientId');
        $amf->shouldReceive('getZone')->once()->globally()->ordered()->with($this->account)->andReturn('zone');
        $zoneConfirmed = false;
        $parser->shouldReceive('parse')->once()->globally()->ordered()->with('zone')->andReturnUsing(function () use (&$zoneConfirmed): array {
            $zoneConfirmed = true;

            return ['errorCode' => 0, 'buildings' => [['id' => 1]]];
        });
        $calls = 0;
        $policy = new ActionRetryPolicy(new GameSessionGuard($recovery), $zones);
        $result = $policy->execute($this->account, 'collect_daily_login_reward', function () use (&$calls, &$zoneConfirmed): string {
            if (++$calls === 1) {
                throw new SessionExpiredException('fixture expired');
            }
            $this->assertTrue($zoneConfirmed);

            return 'ok';
        });
        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    /** @return array<string, array{array<string, mixed>, int, class-string<\Throwable>}> */
    public static function rejectedZones(): array
    {
        return [
            'persistent 1012' => [['errorCode' => 1012], 6, GameServerErrorException::class],
            'expired 1005' => [['errorCode' => 1005], 1, GameServerErrorException::class],
            'other server rejection' => [['errorCode' => 550], 1, GameServerErrorException::class],
            'missing code' => [['buildings' => [['id' => 1]]], 1, RuntimeException::class],
            'malformed code' => [['errorCode' => 'broken', 'buildings' => [['id' => 1]]], 1, RuntimeException::class],
            'empty zone' => [['errorCode' => 0, 'buildings' => []], 1, RuntimeException::class],
        ];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  class-string<\Throwable>  $exception
     */
    #[DataProvider('rejectedZones')]
    public function test_failed_zone_confirmation_never_replays_the_action(array $zone, int $reads, string $exception): void
    {
        [$recovery, $auth, $amf, $parser, $zones] = $this->dependencies();
        $auth->shouldReceive('resetSession')->once();
        // A persistent 1012 after the forced login also takes the game session over once.
        $amf->shouldReceive('invalidateSession')->times($reads === 6 ? 2 : 1);
        $amf->shouldReceive('resetClient')->once();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account, true);
        $amf->shouldNotReceive('forgetClientId');
        $amf->shouldReceive('getZone')->times($reads)->andReturn('zone');
        $parser->shouldReceive('parse')->with('zone')->times($reads)->andReturn($zone);
        $calls = 0;
        try {
            (new ActionRetryPolicy(new GameSessionGuard($recovery), $zones))->execute($this->account, 'produce_buff', function () use (&$calls): string {
                $calls++;
                throw new SessionExpiredException('fixture expired before mutation');
            });
            $this->fail('Expected failed recovery');
        } catch (\Throwable $error) {
            $this->assertInstanceOf($exception, $error);
            $this->assertSame(1, $calls, 'No action callback after rejected zone entry');
        }
    }

    public function test_zone_initializing_retries_only_zone_reads_without_new_login(): void
    {
        [$recovery, $auth, $amf, $parser] = $this->dependencies();
        $auth->shouldNotReceive('ensureAuthenticated');
        $auth->shouldNotReceive('resetSession');
        $amf->shouldNotReceive('forgetClientId');
        $amf->shouldReceive('getZone')->twice()->andReturn('loading', 'ready');
        $parser->shouldReceive('parse')->with('loading')->once()->andReturn(['errorCode' => 1012]);
        $parser->shouldReceive('parse')->with('ready')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $recovery->enterHomeZone($this->account);
        Sleep::assertSleptTimes(1);
    }

    public function test_sync_initial_auth_recovery_consumes_the_only_forced_login_budget(): void
    {
        [$recovery, $auth, $amf, $parser] = $this->dependencies();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account)->andThrow(new SessionExpiredException('fixture expired'));
        $auth->shouldReceive('isCaptchaOr2faError')->andReturnFalse();
        $auth->shouldReceive('resetSession')->once();
        // reset of the forced login + the one fresh-client takeover on 1012
        $amf->shouldReceive('invalidateSession')->twice();
        $amf->shouldReceive('resetClient')->once();
        $amf->shouldNotReceive('forgetClientId');
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account, true);
        $amf->shouldReceive('getZone')->times(6)->andReturn('locked');
        $parser->shouldReceive('parse')->with('locked')->times(6)->andReturn(['errorCode' => 1012]);
        $fetcher = new AccountSyncFetcher($auth, $amf, $parser, $recovery);
        try {
            $fetcher->fetchZone($this->account);
            $this->fail('Expected bounded zone rejection');
        } catch (AccountSyncException $error) {
            $this->assertSame(409, $error->httpStatus());
        }
    }

    public function test_sync_auth_challenge_propagates_without_session_reset_or_second_login(): void
    {
        [$recovery, $auth, $amf, $parser] = $this->dependencies();
        $challenge = AuthChallengeException::captcha();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account)->andThrow($challenge);
        $auth->shouldNotReceive('resetSession');
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldNotReceive('getZone');
        $this->expectExceptionObject($challenge);
        (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
    }

    public function test_sync_initial_network_error_does_not_reset_a_possibly_live_session(): void
    {
        [$recovery, $auth, $amf, $parser] = $this->dependencies();
        $error = new RuntimeException('Network session read timed out');
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account)->andThrow($error);
        $auth->shouldReceive('isCaptchaOr2faError')->andReturnFalse();
        $auth->shouldNotReceive('resetSession');
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldNotReceive('getZone');
        $this->expectExceptionObject($error);
        (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
    }

    public function test_sync_zone_session_busy_does_not_trigger_relogin(): void
    {
        [$recovery, $auth, $amf, $parser] = $this->dependencies();
        $busy = SessionBusyException::forAccount($this->account->id, 'amf', 1);
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account);
        $auth->shouldReceive('isCaptchaOr2faError')->andReturnFalse();
        $auth->shouldNotReceive('resetSession');
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldReceive('getZone')->times(6)->andThrow($busy);
        $this->expectExceptionObject($busy);
        (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
    }
}
