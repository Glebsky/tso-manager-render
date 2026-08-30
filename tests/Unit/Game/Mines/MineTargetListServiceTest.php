<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Models\Account;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\ConfigMineCatalog;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\MinePlacementPolicy;
use App\Services\Game\Mines\MineTargetListService;
use App\Services\Game\Mines\MineUpgradePolicy;
use App\Services\Game\Mines\ZoneSnapshot;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class MineTargetListServiceTest extends TestCase
{
    private Account $account;

    private ConfigMineCatalog $catalog;

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

        $this->catalog = new ConfigMineCatalog([
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
        ]);

        Cache::flush();
    }

    public function test_it_returns_buildable_deposits_with_decisions_and_caches(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [
                    6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000),
                    6432 => new DepositSnapshot(grid: 6432, name: 'Stone', amount: 500, maxAmount: 500),
                ],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        $placementPolicy = new MinePlacementPolicy($this->catalog);
        $upgradePolicy = new MineUpgradePolicy($this->catalog);

        $service = new MineTargetListService($mockZones, $this->catalog, $placementPolicy, $upgradePolicy);

        $list1 = $service->buildableDeposits($this->account);
        $this->assertCount(1, $list1);
        $this->assertSame(6431, $list1[0]['grid']);
        $this->assertSame('IronOre', $list1[0]['deposit_name']);
        $this->assertSame('IronMine', $list1[0]['mine_name']);
        $this->assertSame(1000, $list1[0]['amount']);
        $this->assertSame(1000, $list1[0]['max_amount']);
        $this->assertTrue($list1[0]['allowed']);
        $this->assertSame('ok', $list1[0]['reason']);

        // Second call hits cache (mockZones->forAccount called only once)
        $list2 = $service->buildableDeposits($this->account);
        $this->assertCount(1, $list2);
    }

    public function test_it_returns_upgradable_mines_with_decisions(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [
                    6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false),
                    6432 => new BuildingSnapshot(grid: 6432, name: 'Woodcutter', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false),
                ],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        $placementPolicy = new MinePlacementPolicy($this->catalog);
        $upgradePolicy = new MineUpgradePolicy($this->catalog);

        $service = new MineTargetListService($mockZones, $this->catalog, $placementPolicy, $upgradePolicy);

        $list = $service->upgradableMines($this->account);
        $this->assertCount(1, $list);
        $this->assertSame(6431, $list[0]['grid']);
        $this->assertSame('IronMine', $list[0]['building_name']);
        $this->assertSame('IronOre', $list[0]['deposit_name']);
        $this->assertSame(2, $list[0]['level']);
        $this->assertSame(7, $list[0]['max_level']);
        $this->assertTrue($list[0]['is_active']);
        $this->assertFalse($list[0]['upgrade_in_progress']);
        $this->assertTrue($list[0]['allowed']);
        $this->assertSame('ok', $list[0]['reason']);
    }

    public function test_it_invalidates_cache_on_clear_cache(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->twice()
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [
                    6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000),
                ],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        $placementPolicy = new MinePlacementPolicy($this->catalog);
        $upgradePolicy = new MineUpgradePolicy($this->catalog);

        $service = new MineTargetListService($mockZones, $this->catalog, $placementPolicy, $upgradePolicy);

        $service->buildableDeposits($this->account);

        MineTargetListService::clearCache((int) $this->account->id);

        $service->buildableDeposits($this->account);
    }
}
