<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
use App\Services\Tasks\Execution\ActionRetryPolicy;
use App\Services\Tasks\Execution\GameResponseValidator;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * FR-009 — a single retry/re-login layer (WP-08).
 *
 * Re-login happens only in GameSessionGuard (ActionRetryPolicy delegates to
 * it) and at most once per action; 1012 first takes the superseded game
 * session over like a fresh client; the AMF layer propagates
 * SessionExpiredException instead of logging in itself; the client id
 * survives invalidateSession(); 1012 is detected on the decoded AMF
 * response, not by scanning bytes for "1012".
 */
class RetryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    /** @var TsoAuthService&MockInterface */
    private MockInterface $auth;

    /** @var TsoAmfService&MockInterface */
    private MockInterface $amf;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        $this->account = Account::create([
            'username' => 'retry_policy_user',
            'password' => '[REDACTED]',
            'region' => 'ru',
            'nickname' => 'retry_policy_user',
            'dso_auth_user' => '4001',
            'dso_auth_token' => 'token_retry',
        ]);

        $this->auth = Mockery::mock(TsoAuthService::class);
        $this->amf = Mockery::mock(TsoAmfService::class);
    }

    private function policy(): ActionRetryPolicy
    {
        return new ActionRetryPolicy(new GameSessionGuard(new GameSessionRecovery($this->auth, $this->amf, app(ZoneParserService::class), new VersionedZoneCacheInvalidator(Cache::store('array')), new GameClientIdentity)), new VersionedZoneCacheInvalidator(Cache::store('array')));
    }

    private function allowSessionReset(): void
    {
        $this->auth->shouldReceive('resetSession')->zeroOrMoreTimes();
        $this->amf->shouldReceive('invalidateSession')->zeroOrMoreTimes();
        $this->amf->shouldReceive('resetClient')->zeroOrMoreTimes();
        $this->amf->shouldReceive('getZone')->once()->andReturn('recovered-zone');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('recovered-zone')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $this->app->instance(ZoneParserService::class, $parser);
    }

    // ── ActionRetryPolicy ────────────────────────────────────────────────

    public function test_session_expired_from_transport_triggers_exactly_one_forced_relogin_then_retries(): void
    {
        config(['game.tasks.max_action_attempts' => 3]);
        $this->allowSessionReset();
        $this->auth->shouldReceive('ensureAuthenticated')->once()->with(Mockery::any(), true);

        $calls = 0;
        $result = $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw new SessionExpiredException('Load server rejected the stored session');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function test_persistent_session_expired_relogs_in_once_and_gives_up(): void
    {
        config(['game.tasks.max_action_attempts' => 5]);
        $this->allowSessionReset();
        $this->auth->shouldReceive('ensureAuthenticated')->once()->with(Mockery::any(), true);

        $calls = 0;

        try {
            $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
                $calls++;

                throw new SessionExpiredException('still rejected');
            });
            $this->fail('Expected SessionExpiredException');
        } catch (SessionExpiredException) {
            // FR-009: the second SessionExpiredException after a re-login is final.
            $this->assertSame(2, $calls, 'one attempt before re-login, one after — no more');
        }
    }

    public function test_persistent_1005_relogs_in_at_most_once_regardless_of_attempt_budget(): void
    {
        config(['game.tasks.max_action_attempts' => 6]);
        $this->allowSessionReset();
        $this->auth->shouldReceive('ensureAuthenticated')->once()->with(Mockery::any(), true);

        $calls = 0;

        try {
            $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
                $calls++;

                throw new GameServerErrorException(1005, 'Session expired');
            });
            $this->fail('Expected GameServerErrorException');
        } catch (GameServerErrorException $e) {
            $this->assertSame(1005, $e->getCode());
            $this->assertSame(2, $calls);
        }
    }

    private function zoneReadsSucceed(int $times): void
    {
        $this->amf->shouldReceive('getZone')->times($times)->andReturn('zone');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('zone')->times($times)->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $this->app->instance(ZoneParserService::class, $parser);
    }

    public function test_1012_takes_the_game_session_over_like_a_fresh_client_without_web_login(): void
    {
        config(['game.tasks.max_action_attempts' => 3]);
        $this->zoneReadsSucceed(1);
        // The superseded load-server session (DSId) is dropped, the web token is kept.
        $this->amf->shouldReceive('invalidateSession')->once()->with($this->account->id);
        $this->amf->shouldNotReceive('resetClient');
        $this->amf->shouldNotReceive('ensureZoneLoaded');
        $this->auth->shouldNotReceive('ensureAuthenticated');
        $this->auth->shouldNotReceive('resetSession');
        $identities = new GameClientIdentity;
        $rejected = $identities->current($this->account);

        $calls = 0;
        $result = $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw new GameServerErrorException(1012, 'Newer game session detected');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
        $this->assertNotSame($rejected, $identities->current($this->account));
        Sleep::assertNeverSlept();
    }

    public function test_persistent_1012_takes_over_once_then_uses_the_single_forced_login_and_gives_up(): void
    {
        config(['game.tasks.max_action_attempts' => 6]);
        $this->zoneReadsSucceed(2);
        // takeover + the reset of the single forced login
        $this->amf->shouldReceive('invalidateSession')->twice();
        $this->amf->shouldReceive('resetClient')->once();
        $this->amf->shouldNotReceive('ensureZoneLoaded');
        $this->auth->shouldReceive('resetSession')->once();
        $this->auth->shouldReceive('ensureAuthenticated')->once()->with(Mockery::any(), true);

        $calls = 0;
        try {
            $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
                $calls++;
                throw new GameServerErrorException(1012, 'fixture rejected identity');
            });
            $this->fail('Expected bounded session recovery failure');
        } catch (GameServerErrorException $error) {
            $this->assertSame(1012, $error->getCode());
            $this->assertSame(3, $calls, 'original attempt, after takeover, after the one forced login');
        }
    }

    public function test_takeover_loser_joins_the_winners_session_instead_of_opening_another(): void
    {
        config(['game.tasks.max_action_attempts' => 3]);
        $this->zoneReadsSucceed(1);
        $this->amf->shouldNotReceive('invalidateSession');
        $this->amf->shouldReceive('resetClient')->once()->with($this->account->id);
        $this->auth->shouldNotReceive('ensureAuthenticated');
        $identities = new GameClientIdentity;
        $identities->current($this->account);

        $calls = 0;
        $result = $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                // A parallel worker already took the session over with a new identity.
                Cache::put("tso:client_id:{$this->account->id}", 777, 3600);

                throw new GameServerErrorException(1012, 'Newer game session detected');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(777, $identities->current($this->account), 'the winner identity is kept');
    }

    public function test_nested_guarded_calls_share_the_outer_recovery_budget(): void
    {
        config(['game.tasks.max_action_attempts' => 3]);
        $this->zoneReadsSucceed(1);
        $this->amf->shouldReceive('invalidateSession')->once();
        $this->auth->shouldNotReceive('ensureAuthenticated');
        $guard = new GameSessionGuard(new GameSessionRecovery($this->auth, $this->amf, app(ZoneParserService::class), new VersionedZoneCacheInvalidator(Cache::store('array')), new GameClientIdentity));

        $outerCalls = 0;
        $innerCalls = 0;
        $result = $guard->run($this->account, 'outer', function () use ($guard, &$outerCalls, &$innerCalls): string {
            $outerCalls++;

            return $guard->run($this->account, 'inner', function () use (&$innerCalls): string {
                $innerCalls++;
                if ($innerCalls === 1) {
                    throw new GameServerErrorException(1012, 'Newer game session detected');
                }

                return 'ok';
            });
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $outerCalls, 'the outer guard owns the retry');
        $this->assertSame(2, $innerCalls);
    }

    // ── TsoAmfService ────────────────────────────────────────────────────

    /**
     * @return array{0: TsoAmfService, 1: TsoClientInterface&MockInterface, 2: ZoneParserService&MockInterface}
     */
    private function realAmfService(): array
    {
        $client = Mockery::mock(TsoClientInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);

        return [new TsoAmfService($client, new GameResponseValidator($parser), new GameClientIdentity), $client, $parser];
    }

    public function test_amf_service_propagates_session_expired_without_relogin(): void
    {
        [$service, $client] = $this->realAmfService();

        $client->shouldReceive('sendCommand')->once()->andThrow(new SessionExpiredException('rejected'));
        $this->auth->shouldNotReceive('ensureAuthenticated');
        $this->auth->shouldNotReceive('resetSession');

        $this->expectException(SessionExpiredException::class);

        $service->stopProduction($this->account, 100);
    }

    public function test_invalidate_session_keeps_the_persisted_client_id(): void
    {
        [$service, $client] = $this->realAmfService();

        $key = "tso:client_id:{$this->account->id}";
        Cache::put($key, 123456, 3600);

        $client->shouldReceive('invalidateSession')->once()->with($this->account->id);

        $service->invalidateSession($this->account->id);

        $this->assertSame(123456, Cache::get($key), 'client id must survive a session invalidation');

        // Explicit credential change still drops it.
        $service->forgetClientId($this->account->id);
        $this->assertNull(Cache::get($key));
    }

    public function test_ensure_zone_loaded_ignores_1012_appearing_as_plain_data(): void
    {
        [$service, $client, $parser] = $this->realAmfService();

        // Binary AMF that merely contains the digits "1012" (e.g. a grid id).
        $payload = "\x00\x03grid=1012\x00";
        $client->shouldReceive('sendCommand')->once()->andReturn($payload);
        $parser->shouldReceive('parse')->once()->with($payload)->andReturn(['errorCode' => 0, 'buildings' => []]);

        $result = $service->ensureZoneLoaded($this->account, maxAttempts: 3);

        $this->assertSame($payload, $result);
        Sleep::assertNeverSlept();
    }

    public function test_ensure_zone_loaded_retries_on_decoded_1012(): void
    {
        [$service, $client, $parser] = $this->realAmfService();

        $first = "\x00first";
        $second = "\x00second";
        $client->shouldReceive('sendCommand')->twice()->andReturn($first, $second);
        $parser->shouldReceive('parse')->with($first)->once()->andReturn(['errorCode' => 1012]);
        $parser->shouldReceive('parse')->with($second)->once()->andReturn(['errorCode' => 0]);

        $result = $service->ensureZoneLoaded($this->account, maxAttempts: 3, delaySeconds: 2);

        $this->assertSame($second, $result);
        Sleep::assertSleptTimes(1);
    }
}
