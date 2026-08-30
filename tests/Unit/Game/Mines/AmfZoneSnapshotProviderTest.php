<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Mines\AmfZoneSnapshotProvider;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
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

        $provider = new AmfZoneSnapshotProvider($mockAmf, $mockZones);
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
        $mockZones->expects('parse')->with('raw_amf_zone')->andReturn([
            'errorCode' => 1005,
        ]);

        $this->expectException(GameServerErrorException::class);

        $provider = new AmfZoneSnapshotProvider($mockAmf, $mockZones);
        $provider->forAccount($this->account);
    }
}
