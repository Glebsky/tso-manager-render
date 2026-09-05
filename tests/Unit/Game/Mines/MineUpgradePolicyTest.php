<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Enums\UpgradeRejectionReason;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\ConfigMineCatalog;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\MineUpgradePolicy;
use App\Services\Game\Mines\ZoneSnapshot;
use PHPUnit\Framework\TestCase;

final class MineUpgradePolicyTest extends TestCase
{
    private MineUpgradePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $catalog = new ConfigMineCatalog([
            'BronzeOre' => ['mine' => 'BronzeMine', 'number' => 36, 'max_level' => 7],
            'Coal' => ['mine' => 'CoalMine', 'number' => 37, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'Salpeter' => ['mine' => 'SalpeterMine', 'number' => 63, 'max_level' => 7],
            'TitaniumOre' => ['mine' => 'TitaniumMine', 'number' => 69, 'max_level' => 7],
        ]);

        $this->policy = new MineUpgradePolicy($catalog);
    }

    public function test_it_allows_upgrade_for_mine_in_production_with_available_queue_slot(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertTrue($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::Ok, $decision->reason);
        $this->assertNotNull($decision->definition);
        $this->assertSame('IronMine', $decision->definition->buildingName);
        $this->assertSame(3, $decision->currentLevel);
        $this->assertSame(4, $decision->targetLevel);
    }

    public function test_it_rejects_when_no_building_at_grid(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::NoBuildingAtGrid, $decision->reason);
    }

    public function test_it_rejects_when_building_is_not_a_mine(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'Woodcutter', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::NotAMine, $decision->reason);
    }

    public function test_it_rejects_when_max_level_reached(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 7, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::MaxLevelReached, $decision->reason);
    }

    public function test_it_rejects_when_payload_max_level_reached(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 4, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431, requestedMaxLevel: 4);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::MaxLevelReached, $decision->reason);
    }

    public function test_it_clamps_requested_max_level_to_definition_max_level(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431, requestedMaxLevel: 9);

        $this->assertTrue($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::Ok, $decision->reason);
        $this->assertSame(4, $decision->targetLevel);
    }

    public function test_it_rejects_when_upgrade_already_in_progress(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: true)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::UpgradeAlreadyInProgress, $decision->reason);
    }

    public function test_it_rejects_when_production_inactive(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: false, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::ProductionInactive, $decision->reason);
    }

    public function test_it_rejects_when_build_queue_is_full(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 3, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::BuildQueueFull, $decision->reason);
    }

    public function test_it_rejects_when_building_name_indicates_depleted_mine_ruin(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'MineDepletedDepositIronOre', upgradeLevel: 1, isProductionActive: false, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::MineDepleted, $decision->reason);
    }

    public function test_it_rejects_when_building_is_under_construction(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 0, isProductionActive: false, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::BuildingUnderConstruction, $decision->reason);
    }

    public function test_it_rejects_when_underlying_deposit_is_depleted(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 0, maxAmount: 1000)],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::MineDepleted, $decision->reason);
    }

    public function test_it_rejects_when_underlying_deposit_is_not_accessible(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000, accessible: 0)],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(UpgradeRejectionReason::MineDepleted, $decision->reason);
    }
}
