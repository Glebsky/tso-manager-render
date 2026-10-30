<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\HomeZoneRepository;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
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
 * The persisted home zone is the cache (outbound traffic): reused while fresh,
 * re-read live once it is older than the TTL or was invalidated.
 */
final class HomeZoneRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @var TsoAmfService&MockInterface */
    private MockInterface $amf;

    /** @var ZoneParserService&MockInterface */
    private MockInterface $parser;

    private VersionedZoneCacheInvalidator $zones;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
        $this->amf = Mockery::mock(TsoAmfService::class);
        $this->parser = Mockery::mock(ZoneParserService::class);
        $this->zones = new VersionedZoneCacheInvalidator(Cache::store());
    }

    private function repository(): HomeZoneRepository
    {
        $auth = Mockery::mock(TsoAuthService::class);
        $auth->shouldNotReceive('ensureAuthenticated');
        $guard = new GameSessionGuard(new GameSessionRecovery($auth, $this->amf, $this->parser, $this->zones, new GameClientIdentity));

        return new HomeZoneRepository($this->amf, $this->parser, $guard, $this->zones);
    }

    private function account(?int $syncedSecondsAgo): Account
    {
        return Account::create([
            'username' => 'home_zone_fixture',
            'password' => 'fixture',
            'region' => 'ru',
            'zone_data' => [
                'buildings' => [['buildingGrid' => 1, 'buildingName_string' => 'Stored']],
                'friends' => [['id' => 7, 'username' => 'friend']],
            ],
            'last_sync_at' => $syncedSecondsAgo === null ? null : now()->subSeconds($syncedSecondsAgo),
        ]);
    }

    private function expectLiveRead(int $times = 1): void
    {
        $this->amf->shouldReceive('getZone')->times($times)->andReturn('live');
        $this->parser->shouldReceive('parse')->with('live')->times($times)->andReturn([
            'errorCode' => 0,
            'buildings' => [['buildingGrid' => 2, 'buildingName_string' => 'Live']],
        ]);
    }

    public function test_fresh_persisted_zone_is_served_without_a_game_request(): void
    {
        $this->amf->shouldNotReceive('getZone');
        $account = $this->account(300);

        $zone = $this->repository()->get($account);

        $this->assertSame('Stored', $zone['buildings'][0]['buildingName_string']);
    }

    public function test_zone_older_than_the_ttl_is_read_live_once_and_persisted(): void
    {
        $this->expectLiveRead();
        $account = $this->account(HomeZoneRepository::ttlSeconds() + 5);
        $repository = $this->repository();

        $zone = $repository->get($account);
        // The next reader in the window is served from the database.
        $again = $repository->get($account->refresh());

        $this->assertSame('Live', $zone['buildings'][0]['buildingName_string']);
        $this->assertSame('Live', $again['buildings'][0]['buildingName_string']);
        $stored = $account->refresh();
        $this->assertSame('Live', $stored->zone_data['buildings'][0]['buildingName_string'] ?? null);
        $this->assertSame([['id' => 7, 'username' => 'friend']], $stored->zone_data['friends'] ?? null, 'the synced friend list is kept');
        $this->assertNotNull($stored->last_sync_at);
        $this->assertLessThan(5, abs($stored->last_sync_at->diffInSeconds(now())));
    }

    public function test_never_synced_zone_is_read_live(): void
    {
        $this->expectLiveRead();
        $account = $this->account(null);

        $this->assertSame('Live', $this->repository()->get($account)['buildings'][0]['buildingName_string']);
    }

    public function test_home_zone_invalidation_distrusts_the_persisted_zone(): void
    {
        $this->expectLiveRead();
        $account = $this->account(30);
        $this->zones->invalidateHomeZone($account->id);

        $this->assertSame('Live', $this->repository()->get($account)['buildings'][0]['buildingName_string']);
    }

    public function test_short_lived_readers_can_require_a_younger_zone(): void
    {
        $account = $this->account(90);
        $repository = $this->repository();

        $this->assertNotNull($repository->fresh($account));
        $this->assertNull($repository->fresh($account, 60));
    }

    public function test_zero_ttl_disables_the_cache(): void
    {
        config(['game.home_zone_cache_ttl_seconds' => 0]);
        $this->expectLiveRead();
        $account = $this->account(1);

        $this->assertSame('Live', $this->repository()->get($account)['buildings'][0]['buildingName_string']);
    }

    public function test_force_refresh_always_reads_live(): void
    {
        $this->expectLiveRead();
        $account = $this->account(1);

        $this->assertSame('Live', $this->repository()->get($account, forceRefresh: true)['buildings'][0]['buildingName_string']);
    }

    public function test_live_read_takes_a_superseded_session_over_through_the_guard(): void
    {
        $account = $this->account(null);
        $this->amf->shouldReceive('invalidateSession')->once()->with($account->id);
        // rejected read, takeover zone confirmation, retried read
        $this->amf->shouldReceive('getZone')->times(3)->andReturn('superseded', 'live', 'live');
        $this->parser->shouldReceive('parse')->with('superseded')->once()->andReturn(['errorCode' => 1012]);
        $this->parser->shouldReceive('parse')->with('live')->twice()->andReturn([
            'errorCode' => 0,
            'buildings' => [['buildingGrid' => 2, 'buildingName_string' => 'Live']],
        ]);

        $this->assertSame('Live', $this->repository()->get($account)['buildings'][0]['buildingName_string']);
    }

    public function test_non_session_game_error_is_thrown_and_nothing_is_persisted(): void
    {
        $account = $this->account(null);
        $this->amf->shouldReceive('getZone')->once()->andReturn('broken');
        $this->parser->shouldReceive('parse')->with('broken')->once()->andReturn(['errorCode' => 550]);

        try {
            $this->repository()->refresh($account);
            $this->fail('Expected the game error');
        } catch (GameServerErrorException $error) {
            $this->assertSame(550, $error->getCode());
        }

        $this->assertSame('Stored', $account->refresh()->zone_data['buildings'][0]['buildingName_string'] ?? null);
    }

    public function test_get_falls_back_to_stale_zone_when_live_read_fails_with_1001(): void
    {
        config(['game.tasks.max_action_attempts' => 1]);
        $account = $this->account(1200); // Older than TTL (600s), but younger than stale window (3600s)
        $this->amf->shouldReceive('getZone')->once()->andReturn('initializing');
        $this->parser->shouldReceive('parse')->with('initializing')->once()->andReturn(['errorCode' => 1001]);

        $zone = $this->repository()->get($account);

        $this->assertSame('Stored', $zone['buildings'][0]['buildingName_string']);
    }

    public function test_get_rethrows_1001_when_stale_fallback_is_disabled(): void
    {
        config(['game.tasks.max_action_attempts' => 1]);
        $account = $this->account(1200);
        $this->amf->shouldReceive('getZone')->once()->andReturn('initializing');
        $this->parser->shouldReceive('parse')->with('initializing')->once()->andReturn(['errorCode' => 1001]);

        $this->expectException(GameServerErrorException::class);
        $this->expectExceptionCode(1001);

        $this->repository()->get($account, allowStaleFallback: false);
    }

    public function test_get_rethrows_1001_when_persisted_zone_is_older_than_max_stale_age(): void
    {
        config(['game.tasks.max_action_attempts' => 1]);
        $account = $this->account(4000); // Older than maxStaleAgeSeconds (3600s)
        $this->amf->shouldReceive('getZone')->once()->andReturn('initializing');
        $this->parser->shouldReceive('parse')->with('initializing')->once()->andReturn(['errorCode' => 1001]);

        $this->expectException(GameServerErrorException::class);
        $this->expectExceptionCode(1001);

        $this->repository()->get($account);
    }
}
