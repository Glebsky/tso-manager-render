<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccountSyncException;
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
use Tests\TestCase;

final class GameClientIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->account = Account::create(['username' => 'identity_fixture', 'password' => 'fixture', 'region' => 'ru', 'dso_auth_token' => 'fixture-token']);
    }

    public function test_identity_is_shared_and_only_one_compare_and_swap_wins(): void
    {
        $first = new GameClientIdentity;
        $other = new GameClientIdentity;
        $old = $first->current($this->account);
        $this->assertSame($old, $other->current($this->account));
        $this->assertTrue($first->renew($this->account, $old));
        $new = $first->current($this->account);
        $this->assertNotSame($old, $new);
        $this->assertGreaterThanOrEqual(1, $new);
        $this->assertLessThanOrEqual(2147483646, $new);
        $this->assertFalse($other->renew($this->account, $old));
        $this->assertSame($new, $other->current($this->account));
        $this->assertSame('fixture-token', $this->account->refresh()->dso_auth_token);
    }

    public function test_game_identity_conflict_recovers_without_any_web_login(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $auth->shouldNotReceive('resetSession');
        $amf = Mockery::mock(TsoAmfService::class);
        // Fresh-client takeover: the superseded load-server session is dropped at once.
        $amf->shouldReceive('invalidateSession')->once()->with($this->account->id);
        $amf->shouldNotReceive('resetClient');
        $amf->shouldNotReceive('ensureZoneLoaded');
        $amf->shouldReceive('getZone')->once()->andReturn('confirmed-zone');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('confirmed-zone')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identities = new GameClientIdentity;
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identities);
        $old = $identities->current($this->account);
        $calls = 0;
        $result = (new ActionRetryPolicy(new GameSessionGuard($recovery), $zones))->execute($this->account, 'collect_daily_login_reward', function () use (&$calls, $identities, $old): string {
            if (++$calls === 1) {
                $this->assertSame($old, $identities->current($this->account));
                throw new GameServerErrorException(1012, 'fixture newer session');
            }
            $this->assertNotSame($old, $identities->current($this->account));

            return 'confirmed';
        });
        $this->assertSame('confirmed', $result);
        $this->assertSame(2, $calls);
        $this->assertSame('fixture-token', $this->account->refresh()->dso_auth_token);
    }

    public function test_sync_uses_identity_recovery_without_forced_web_login(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account);
        $auth->shouldNotReceive('resetSession');
        $amf = Mockery::mock(TsoAmfService::class);
        $amf->shouldReceive('invalidateSession')->once()->with($this->account->id);
        $amf->shouldNotReceive('resetClient');
        $amf->shouldReceive('getZone')->twice()->andReturn('locked', 'ready');
        $amf->shouldReceive('getFriendList')->once()->andReturn('friends');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('locked')->once()->andReturn(['errorCode' => 1012]);
        $parser->shouldReceive('parse')->with('ready')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $parser->shouldReceive('parse')->with('friends')->once()->andReturn(['friends' => []]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $result = (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
        $this->assertSame(0, $result['errorCode']);
        $this->assertNotSame($old, $identity->current($this->account));
        $this->assertSame('fixture-token', $this->account->refresh()->dso_auth_token);
        Sleep::assertNeverSlept();
    }

    public function test_sync_persistent_1012_after_takeover_uses_the_single_forced_login(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account);
        $auth->shouldReceive('resetSession')->once();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account, true);
        $amf = Mockery::mock(TsoAmfService::class);
        // takeover + the reset of the forced login
        $amf->shouldReceive('invalidateSession')->twice();
        $amf->shouldReceive('resetClient')->once();
        $amf->shouldReceive('getZone')->times(3)->andReturn('locked', 'locked', 'ready');
        $amf->shouldReceive('getFriendList')->once()->andReturn('friends');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('locked')->twice()->andReturn(['errorCode' => 1012]);
        $parser->shouldReceive('parse')->with('ready')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $parser->shouldReceive('parse')->with('friends')->once()->andReturn(['friends' => []]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $result = (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
        $this->assertSame(0, $result['errorCode']);
        $this->assertNotSame($old, $identity->current($this->account));
    }

    public function test_rejected_identity_after_confirmed_recovery_does_not_rotate_again(): void
    {
        config(['game.tasks.max_action_attempts' => 6]);
        $auth = Mockery::mock(TsoAuthService::class);
        // The rejected fresh session uses the single forced login, never a second rotation.
        $auth->shouldReceive('resetSession')->once();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account, true);
        $amf = Mockery::mock(TsoAmfService::class);
        $amf->shouldReceive('invalidateSession')->twice();
        $amf->shouldReceive('resetClient')->once();
        $amf->shouldNotReceive('ensureZoneLoaded');
        $amf->shouldReceive('getZone')->twice()->andReturn('zone');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('zone')->twice()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $observed = [];
        try {
            (new ActionRetryPolicy(new GameSessionGuard($recovery), $zones))->execute($this->account, 'produce_buff', function () use (&$observed, $identity): string {
                $observed[] = $identity->current($this->account);
                throw new GameServerErrorException(1012, 'fixture conflict persists');
            });
            $this->fail('Expected bounded rejection');
        } catch (GameServerErrorException $error) {
            $this->assertSame(1012, $error->getCode());
            $this->assertCount(3, $observed);
            $this->assertNotSame($observed[0], $observed[1]);
            $this->assertSame($observed[1], $observed[2]);
            $this->assertSame($observed[2], $identity->current($this->account));
        }
    }

    public function test_busy_amf_lock_never_rotates_identity_or_releases_foreign_lock(): void
    {
        config(['game.session_lock_wait_seconds' => 0]);
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $foreign = Cache::lock('tso:amf_lock:'.$this->account->id, 130);
        $this->assertTrue($foreign->get());
        try {
            $identity->renew($this->account, $old);
            $this->fail('Expected busy identity recovery');
        } catch (SessionBusyException) {
            $this->assertSame($old, $identity->current($this->account));
            $this->assertFalse(Cache::lock('tso:amf_lock:'.$this->account->id, 130)->get());
        } finally {
            $foreign->release();
        }
    }

    public function test_invalid_cached_identity_is_rejected_without_silent_rotation(): void
    {
        Cache::put('tso:client_id:'.$this->account->id, 'invalid-fixture', 60);
        $this->expectException(\RuntimeException::class);
        (new GameClientIdentity)->current($this->account);
    }

    public function test_expired_web_session_then_game_conflict_uses_one_login_and_one_identity_recovery(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('resetSession')->once();
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account, true);
        $amf = Mockery::mock(TsoAmfService::class);
        // reset of the forced login + one takeover when the zone stays superseded
        $amf->shouldReceive('invalidateSession')->twice();
        $amf->shouldReceive('resetClient')->once();
        $amf->shouldReceive('getZone')->times(4)->andReturn('locked', 'locked', 'locked', 'ready');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('locked')->times(3)->andReturn(['errorCode' => 1012]);
        $parser->shouldReceive('parse')->with('ready')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $calls = 0;
        $result = (new ActionRetryPolicy(new GameSessionGuard($recovery), $zones))->execute($this->account, 'collect_daily_login_reward', function () use (&$calls): string {
            if (++$calls === 1) {
                throw new SessionExpiredException('fixture web session expired');
            }

            return 'ok';
        });
        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
        $this->assertNotSame($old, $identity->current($this->account));
    }

    public function test_parallel_identity_recovery_reuses_the_winner_without_rotating_twice(): void
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $auth->shouldNotReceive('resetSession');
        $amf = Mockery::mock(TsoAmfService::class);
        // The loser joins the winner's session: no second load-server session.
        $amf->shouldNotReceive('invalidateSession');
        $amf->shouldReceive('resetClient')->once();
        $amf->shouldNotReceive('ensureZoneLoaded');
        $amf->shouldReceive('getZone')->once()->andReturn('ready');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('ready')->once()->andReturn(['errorCode' => 0, 'buildings' => [['id' => 1]]]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $winner = null;
        $calls = 0;
        $result = (new ActionRetryPolicy(new GameSessionGuard($recovery), $zones))->execute($this->account, 'collect_daily_login_reward', function () use (&$calls, &$winner, $identity, $old): string {
            $calls++;
            if ($calls === 1) {
                // A parallel worker wins the takeover of the same rejected identity.
                $this->assertTrue((new GameClientIdentity)->renew($this->account, $old));
                $winner = $identity->current($this->account);

                throw new GameServerErrorException(1012, 'fixture rejected older identity');
            }
            $this->assertSame($winner, $identity->current($this->account));

            return 'ok';
        });
        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
        $this->assertSame($winner, $identity->current($this->account));
    }

    public function test_sync_stops_when_identity_recovery_lock_is_busy(): void
    {
        config(['game.session_lock_wait_seconds' => 0]);
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldReceive('ensureAuthenticated')->once()->with($this->account);
        $auth->shouldNotReceive('resetSession');
        $amf = Mockery::mock(TsoAmfService::class);
        // The first 1012 already needs the takeover; a busy lock stops the sync.
        $amf->shouldReceive('getZone')->once()->andReturn('locked');
        $amf->shouldNotReceive('resetClient');
        $amf->shouldNotReceive('invalidateSession');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('locked')->once()->andReturn(['errorCode' => 1012]);
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $identity = new GameClientIdentity;
        $old = $identity->current($this->account);
        $recovery = new GameSessionRecovery($auth, $amf, $parser, $zones, $identity);
        $foreign = Cache::lock('tso:amf_lock:'.$this->account->id, 130);
        $this->assertTrue($foreign->get());
        try {
            (new AccountSyncFetcher($auth, $amf, $parser, $recovery))->fetchZone($this->account);
            $this->fail('Expected bounded busy recovery');
        } catch (AccountSyncException $error) {
            $this->assertSame(409, $error->httpStatus());
            $this->assertInstanceOf(SessionBusyException::class, $error->getPrevious());
            $this->assertSame($old, $identity->current($this->account));
            $this->assertFalse(Cache::lock('tso:amf_lock:'.$this->account->id, 130)->get());
        } finally {
            $foreign->release();
        }
    }
}
