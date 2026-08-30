<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CollectibleKind;
use App\Services\Game\ClickableBuildingRegistry;
use Tests\TestCase;

class ClickableBuildingRegistryTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $patterns
     */
    private function registry(array $patterns = []): ClickableBuildingRegistry
    {
        if ($patterns === []) {
            $patterns = [
                ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
                ['pattern' => '/^StarfallStarDust.*$/i', 'kind' => 1],
            ];
        }

        return new ClickableBuildingRegistry($patterns);
    }

    public function test_classifies_collectible_building_as_normal(): void
    {
        $registry = $this->registry();

        $this->assertSame(CollectibleKind::Normal, $registry->classify('CollectibleHerbsBuilding'));
        $this->assertSame(CollectibleKind::Normal, $registry->classify('collectiblefoodbuilding'));
        $this->assertTrue($registry->isClickable('CollectibleHerbsBuilding'));
    }

    public function test_classifies_starfall_as_event(): void
    {
        $registry = $this->registry();

        $this->assertSame(CollectibleKind::Event, $registry->classify('StarfallStarDust_1'));
        $this->assertSame(CollectibleKind::Event, $registry->classify('starfallstardust'));
        $this->assertTrue($registry->isClickable('StarfallStarDust'));
    }

    public function test_returns_null_for_regular_or_quest_buildings(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->classify('Woodcutter'));
        $this->assertNull($registry->classify('FlyingHouse'));
        $this->assertNull($registry->classify('ChristmasTree'));
        $this->assertNull($registry->classify('MayorHouse'));
        $this->assertFalse($registry->isClickable('Woodcutter'));
        $this->assertFalse($registry->isClickable('FlyingHouse'));
    }

    public function test_returns_null_for_empty_string(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->classify(''));
        $this->assertFalse($registry->isClickable(''));
    }

    public function test_handles_broken_regex_gracefully(): void
    {
        $registry = new ClickableBuildingRegistry([
            ['pattern' => '(/invalid-regex[', 'kind' => 0],
            ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
        ]);

        $this->assertSame(CollectibleKind::Normal, $registry->classify('CollectibleHerbsBuilding'));
        $this->assertNull($registry->classify('SomeOtherBuilding'));
    }

    public function test_patterns_returns_list_of_patterns(): void
    {
        $registry = $this->registry();

        $this->assertSame([
            '/^Collectible.+Building$/i',
            '/^StarfallStarDust.*$/i',
        ], $registry->patterns());
    }
}
