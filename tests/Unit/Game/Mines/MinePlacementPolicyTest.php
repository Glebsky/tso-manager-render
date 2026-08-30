<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Enums\PlacementRejectionReason;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\ConfigMineCatalog;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\MinePlacementPolicy;
use App\Services\Game\Mines\ZoneSnapshot;
use PHPUnit\Framework\TestCase;

final class MinePlacementPolicyTest extends TestCase
{
    private MinePlacementPolicy $policy;

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

        $this->policy = new MinePlacementPolicy($catalog);
    }

    public function test_it_allows_placement_on_discovered_empty_deposit_with_available_queue_slot(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertTrue($decision->allowed);
        $this->assertSame(PlacementRejectionReason::Ok, $decision->reason);
        $this->assertNotNull($decision->definition);
        $this->assertSame('IronMine', $decision->definition->buildingName);
        $this->assertSame(50, $decision->definition->buildingNumber);
    }

    public function test_it_rejects_when_no_deposit_at_grid(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::NoDepositAtGrid, $decision->reason);
        $this->assertNull($decision->definition);
    }

    public function test_it_rejects_unknown_deposit_type(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'Stone', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::UnknownDepositType, $decision->reason);
        $this->assertNull($decision->definition);
    }

    public function test_it_rejects_depleted_deposit(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 0, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::DepositEmpty, $decision->reason);
        $this->assertNotNull($decision->definition);
    }

    public function test_it_rejects_when_grid_occupied_by_building(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::GridOccupied, $decision->reason);
    }

    public function test_it_rejects_when_build_queue_is_full(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 3, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::BuildQueueFull, $decision->reason);
    }

    public function test_it_rejects_when_build_queue_is_null(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: null,
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::BuildQueueFull, $decision->reason);
    }

    public function test_it_reports_occupied_grid_before_queue_limits(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false)],
            buildQueue: new BuildQueueSnapshot(used: 3, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertSame(PlacementRejectionReason::GridOccupied, $decision->reason);
    }

    public function test_it_never_returns_a_definition_when_rejected_for_unknown_deposit(): void
    {
        $zone = new ZoneSnapshot(
            depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'UnknownDeposit', amount: 1000, maxAmount: 1000)],
            buildingsByGrid: [],
            buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
        );

        $decision = $this->policy->decide($zone, 6431);

        $this->assertFalse($decision->allowed);
        $this->assertNull($decision->definition);
    }
}
