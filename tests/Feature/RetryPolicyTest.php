<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Amf\Transport\TsoClientInterface;
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
 * Re-login happens only in ActionRetryPolicy and at most once per action;
 * the AMF layer propagates SessionExpiredException instead of logging in
 * itself; the client id survives invalidateSession(); 1012 is detected on
 * the decoded AMF response, not by scanning bytes for "1012".
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
        return new ActionRetryPolicy($this->auth, $this->amf, Cache::store('array'));
    }

    private function allowSessionReset(): void
    {
        $this->auth->shouldReceive('resetSession')->zeroOrMoreTimes();
        $this->amf->shouldReceive('invalidateSession')->zeroOrMoreTimes();
        $this->amf->shouldReceive('resetClient')->zeroOrMoreTimes();
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

    public function test_persistent_1012_warms_up_once_relogs_in_once_and_gives_up(): void
    {
        config(['game.tasks.max_action_attempts' => 6]);
        $this->allowSessionReset();
        $this->amf->shouldReceive('ensureZoneLoaded')->once()->andReturn('zone');
        $this->auth->shouldReceive('ensureAuthenticated')->once()->with(Mockery::any(), true);

        $calls = 0;

        try {
            $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
                $calls++;

                throw new GameServerErrorException(1012, 'Newer game session detected');
            });
            $this->fail('Expected GameServerErrorException');
        } catch (GameServerErrorException $e) {
            $this->assertSame(1012, $e->getCode());
            // attempt 1: warm-up; attempt 2: re-login; attempt 3: fails → stop.
            $this->assertSame(3, $calls);
        }
    }

    public function test_1012_then_success_after_warm_up_does_not_relogin(): void
    {
        config(['game.tasks.max_action_attempts' => 3]);
        $this->amf->shouldReceive('resetClient')->once();
        $this->amf->shouldReceive('ensureZoneLoaded')->once()->andReturn('zone');
        $this->auth->shouldNotReceive('ensureAuthenticated');
        $this->auth->shouldNotReceive('resetSession');
        $this->amf->shouldNotReceive('invalidateSession');

        $calls = 0;
        $result = $this->policy()->execute($this->account, 'stop_production', function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                throw new GameServerErrorException(1012, 'Zone loading');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    // ── TsoAmfService ────────────────────────────────────────────────────

    /**
     * @return array{0: TsoAmfService, 1: TsoClientInterface&MockInterface, 2: ZoneParserService&MockInterface}
     */
    private function realAmfService(): array
    {
        $client = Mockery::mock(TsoClientInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);

        return [new TsoAmfService($client, new GameResponseValidator($parser)), $client, $parser];
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
