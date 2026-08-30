<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Enums\ProductionRejectionReason;
use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Support\Zone\ZoneSnapshot;
use PHPUnit\Framework\TestCase;

final class ProductionOrderPolicyTest extends TestCase
{
    private ProductionOrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $catalog = new ConfigProductionCatalog([
            'producers' => [
                'ProvisionHouse' => 1,
                'Bookbinder' => 2,
            ],
            'recipes' => [
                1 => [
                    [
                        'name' => 'ProductivityBuffLvl3',
                        'group' => 0,
                        'duration_seconds' => 1800,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 0,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'Fish', 'count' => 120],
                            ['resource' => 'Bread', 'count' => 60],
                            ['resource' => 'Sausage', 'count' => 20],
                        ],
                        'costs_known' => true,
                    ],
                ],
                2 => [
                    [
                        'name' => 'Tome',
                        'group' => 0,
                        'duration_seconds' => 3600,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 2,
                        'requires_upgrade_level_max' => 5,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'IntermediatePaper', 'count' => 200],
                        ],
                        'costs_known' => true,
                    ],
                ],
            ],
        ]);

        $this->policy = new ProductionOrderPolicy($catalog);
    }

    public function test_it_allows_valid_order_with_empty_queue(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 3,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 2);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
        $this->assertNotNull($decision->recipe);
        $this->assertSame('ProductivityBuffLvl3', $decision->recipe->name);
        $this->assertSame(1, $decision->productionType);
    }

    public function test_it_rejects_when_building_not_found(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                ['buildingGrid' => 9999, 'buildingName_string' => 'ProvisionHouse'],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::BuildingNotFound, $decision->reason);
    }

    public function test_it_rejects_when_building_is_not_a_producer(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                ['buildingGrid' => 1234, 'buildingName_string' => 'Woodcutter'],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::NotAProducer, $decision->reason);
    }

    public function test_it_rejects_on_production_type_mismatch(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                ['buildingGrid' => 1234, 'buildingName_string' => 'Bookbinder'], // type is 2
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1); // expected 1

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::ProductionTypeMismatch, $decision->reason);
    }

    public function test_it_rejects_when_building_is_upgrading(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => true,
                ],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::BuildingUpgrading, $decision->reason);
    }

    public function test_it_rejects_when_recipe_is_unknown(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'NonExistentRecipe', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::RecipeUnknown, $decision->reason);
    }

    public function test_it_rejects_when_recipe_level_is_locked_for_timed_lists(): void
    {
        // Bookbinder with level 1 (min is 2) -> rejected
        $snapshotLow = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 2000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 1,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $decisionLow = $this->policy->decide($snapshotLow, 2000, 2, 'Tome', 1);
        $this->assertFalse($decisionLow->allowed);
        $this->assertSame(ProductionRejectionReason::RecipeLevelLocked, $decisionLow->reason);

        // Bookbinder with level 6 (max is 5) -> rejected
        $snapshotHigh = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 2000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 6,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $decisionHigh = $this->policy->decide($snapshotHigh, 2000, 2, 'Tome', 1);
        $this->assertFalse($decisionHigh->allowed);
        $this->assertSame(ProductionRejectionReason::RecipeLevelLocked, $decisionHigh->reason);

        // Boundary checks: level 2 and level 5 -> allowed
        $snapshotMin = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 2000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 2,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);
        $this->assertTrue($this->policy->decide($snapshotMin, 2000, 2, 'Tome', 1)->allowed);

        $snapshotMax = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 2000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 5,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);
        $this->assertTrue($this->policy->decide($snapshotMax, 2000, 2, 'Tome', 1)->allowed);

        // Null level -> not rejected
        $snapshotNullLvl = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 2000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);
        $this->assertTrue($this->policy->decide($snapshotNullLvl, 2000, 2, 'Tome', 1)->allowed);
    }

    public function test_type_1_buff_pool_recipes_never_locked_by_upgrade_level(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 0,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1);
        $this->assertTrue($decision->allowed);
    }

    public function test_it_rejects_when_queue_data_is_unavailable(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => false,
                ],
            ],
            // 'production_queues' missing
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::QueueDataUnavailable, $decision->reason);
    }

    public function test_priority_reports_building_not_found_before_recipe_unknown(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [],
            'production_queues' => [],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'NonExistentRecipe', 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::BuildingNotFound, $decision->reason);
    }

    public function test_it_rejects_when_resources_are_insufficient(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 3,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
            'resources' => [
                ['name_string' => 'Bread', 'amount' => 1000],
                ['name_string' => 'Fish', 'amount' => 100], // Needs 120 * 2 = 240
                ['name_string' => 'Sausage', 'amount' => 500],
            ],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 2);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ProductionRejectionReason::InsufficientResources, $decision->reason);
    }

    public function test_it_allows_when_resources_are_sufficient(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 3,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
            'resources' => [
                ['name_string' => 'Bread', 'amount' => 1000],
                ['name_string' => 'Fish', 'amount' => 500], // Needs 120 * 2 = 240
                ['name_string' => 'Sausage', 'amount' => 500],
            ],
        ]);

        $decision = $this->policy->decide($snapshot, 1234, 1, 'ProductivityBuffLvl3', 2);

        $this->assertTrue($decision->allowed);
        $this->assertNull($decision->reason);
    }
}
