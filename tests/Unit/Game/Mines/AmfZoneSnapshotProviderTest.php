<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Mines\AmfZoneSnapshotProvider;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\HomeZoneRepository;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AmfZoneSnapshotProviderTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = new Account([
            'username' => 'test_user',
            'password' => 'secret',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);
        $this->account->id = 1;
    }

    public function test_it_parses_zone_data_into_typed_snapshot(): void
    {
        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('getZone')->with($this->account)->andReturn('raw_amf_zone');

        /** @var ZoneParserService&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneParserService::class);
        $mockZones->expects('parse')->with('raw_amf_zone')->andReturn([
            'errorCode' => 0,
            'deposits' => [
                ['grid' => 6431, 'name' => 'IronOre', 'amount' => 12400, 'max_amount' => 15000, 'accessible' => 0],
                ['grid' => 0, 'name' => 'InvalidDeposit', 'amount' => 100],
                'invalid_string_entry',
            ],
            'buildings' => [
                [
                    'buildingGrid' => 6431,
                    'buildingName_string' => 'IronMine',
                    'upgradeLevel' => 3,
                    'isProductionActive' => true,
                    'upgradeIsInProgress' => false,
                ],
                'invalid_string_entry',
            ],
            'build_queue' => [
                'used' => 2,
                'total' => 5,
            ],
        ]);

        $provider = $this->provider($mockAmf, $mockZones);
        $snapshot = $provider->forAccount($this->account);

        $deposit = $snapshot->depositAt(6431);
        $this->assertNotNull($deposit);
        $this->assertSame('IronOre', $deposit->name);
        $this->assertSame(12400, $deposit->amount);
        $this->assertSame(15000, $deposit->maxAmount);
        $this->assertSame(0, $deposit->accessible);

        $this->assertNull($snapshot->depositAt(0));

        $building = $snapshot->buildingAt(6431);
        $this->assertNotNull($building);
        $this->assertSame('IronMine', $building->name);
        $this->assertSame(3, $building->upgradeLevel);
        $this->assertTrue($building->isProductionActive);
        $this->assertFalse($building->upgradeInProgress);

        $this->assertSame(3, $snapshot->buildQueueBudget()->freeSlots());
    }

    public function test_it_throws_on_zone_error_code(): void
    {
        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('getZone')->with($this->account)->andReturn('raw_amf_zone');

        /** @var ZoneParserService&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneParserService::class);
        // A non-session game error (session errors are recovered by GameSessionGuard).
        $mockZones->expects('parse')->with('raw_amf_zone')->andReturn([
            'errorCode' => 550,
        ]);

        $this->expectException(GameServerErrorException::class);
        $this->expectExceptionCode(550);

        $provider = $this->provider($mockAmf, $mockZones);
        $provider->forAccount($this->account);
    }

    public function test_it_preserves_existing_friends_when_updating_account_zone_data(): void
    {
        $existingFriends = [
            ['id' => 12345, 'nickname' => 'MyFriend'],
        ];

        $account = Mockery::mock(Account::class)->makePartial();
        $account->exists = true;
        $account->id = 99;
        $account->zone_data = ['friends' => $existingFriends];

        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('getZone')->with($account)->andReturn('raw_amf_zone');

        /** @var ZoneParserService&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneParserService::class);
        $mockZones->expects('parse')->with('raw_amf_zone')->andReturn([
            'errorCode' => 0,
            'buildings' => [],
        ]);

        $account->expects('update')
            ->once()
            ->with(Mockery::on(function ($attributes) use ($existingFriends) {
                $decoded = json_decode($attributes['zone_data'], true);

                return isset($decoded['friends']) && $decoded['friends'] === $existingFriends;
            }))
            ->andReturn(true);

        $provider = $this->provider($mockAmf, $mockZones);
        $provider->forAccount($account, forceRefresh: true);
    }

    private function provider(TsoAmfService $amf, ZoneParserService $parser): AmfZoneSnapshotProvider
    {
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $guard = new GameSessionGuard(new GameSessionRecovery(Mockery::mock(TsoAuthService::class), $amf, $parser, $zones, new GameClientIdentity));

        return new AmfZoneSnapshotProvider(new HomeZoneRepository($amf, $parser, $guard, $zones));
    }
}
