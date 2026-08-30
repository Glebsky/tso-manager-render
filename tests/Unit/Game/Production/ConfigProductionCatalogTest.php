<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionRecipe;
use PHPUnit\Framework\TestCase;

final class ConfigProductionCatalogTest extends TestCase
{
    private ConfigProductionCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'producers' => [
                'ProvisionHouse' => 1,
                'Bookbinder' => 2,
                'ProvisionHouse2' => 5,
                'Laboratory' => 16,
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
                    [
                        'name' => 'FreeBuffNoCost',
                        'group' => 0,
                        'duration_seconds' => 60,
                        'buff_type' => 'Instant',
                        'requires_upgrade_level_min' => 0,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [],
                        'costs_known' => false,
                    ],
                ],
                2 => [
                    [
                        'name' => 'Tome',
                        'group' => 0,
                        'duration_seconds' => 3600,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 2,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'IntermediatePaper', 'count' => 200],
                        ],
                        'costs_known' => true,
                    ],
                ],
            ],
        ];

        $this->catalog = new ConfigProductionCatalog($config);
    }

    public function test_production_type_for_known_and_unknown_buildings(): void
    {
        $this->assertSame(1, $this->catalog->productionTypeFor('ProvisionHouse'));
        $this->assertSame(2, $this->catalog->productionTypeFor('Bookbinder'));
        $this->assertSame(5, $this->catalog->productionTypeFor('ProvisionHouse2'));
        $this->assertSame(16, $this->catalog->productionTypeFor('Laboratory'));
        $this->assertNull($this->catalog->productionTypeFor('Woodcutter'));
        $this->assertNull($this->catalog->productionTypeFor('IronMine'));
    }

    public function test_recipes_for_existing_and_non_existing_types(): void
    {
        $recipes1 = $this->catalog->recipesFor(1);
        $this->assertCount(2, $recipes1);
        $this->assertInstanceOf(ProductionRecipe::class, $recipes1[0]);
        $this->assertSame('ProductivityBuffLvl3', $recipes1[0]->name);
        $this->assertSame(3, count($recipes1[0]->costs));
        $this->assertTrue($recipes1[0]->costsKnown);

        $this->assertSame('FreeBuffNoCost', $recipes1[1]->name);
        $this->assertSame([], $recipes1[1]->costs);
        $this->assertFalse($recipes1[1]->costsKnown);

        // Absent production type (e.g. 3, 999)
        $this->assertSame([], $this->catalog->recipesFor(3));
        $this->assertSame([], $this->catalog->recipesFor(999));
    }

    public function test_find_recipe(): void
    {
        $recipe = $this->catalog->findRecipe(1, 'ProductivityBuffLvl3');
        $this->assertNotNull($recipe);
        $this->assertSame('ProductivityBuffLvl3', $recipe->name);
        $this->assertSame(1800, $recipe->durationSeconds);
        $this->assertCount(3, $recipe->costs);

        $this->assertNull($this->catalog->findRecipe(1, 'NoSuchRecipe'));
        $this->assertNull($this->catalog->findRecipe(2, 'ProductivityBuffLvl3'));
    }

    public function test_all_producers(): void
    {
        $producers = $this->catalog->allProducers();
        $this->assertCount(4, $producers);
        $this->assertArrayHasKey('ProvisionHouse', $producers);
        $this->assertSame(1, $producers['ProvisionHouse']);
    }
}
