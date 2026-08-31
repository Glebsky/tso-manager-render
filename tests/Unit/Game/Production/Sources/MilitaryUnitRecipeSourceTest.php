<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Sources;

use App\Services\Game\Production\Sources\MilitaryUnitRecipeSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MilitaryUnitRecipeSourceTest extends TestCase
{
    private string $xml = <<<'XML'
<MilitaryUnits>
    <MilitaryUnit type="Recruit" produceable="true" productionTimeSeconds="180" instantBuildCosts="10">
        <costs>
            <cost name="BronzeSword" count="10" />
            <cost name="Beer" count="5" />
            <cost name="Population" count="1" />
        </costs>
    </MilitaryUnit>
    <MilitaryUnit type="EliteSoldier" produceable="true" productionTimeSeconds="600" instantBuildCosts="20">
        <costs>
            <cost name="DamasceneSword" count="10" />
            <cost name="Beer" count="50" />
            <cost name="Population" count="1" />
        </costs>
    </MilitaryUnit>
    <MilitaryUnit type="Swordsman" produceable="true" isElite="true" productionTimeSeconds="300" instantBuildCosts="15">
        <costs>
            <cost name="PlatinumSword" count="10" />
            <cost name="Beer" count="20" />
            <cost name="Population" count="1" />
        </costs>
    </MilitaryUnit>
    <MilitaryUnit type="UnproduceableUnit" produceable="false" productionTimeSeconds="100">
        <costs />
    </MilitaryUnit>
</MilitaryUnits>
XML;

    public function test_requires_boolean_elite_option(): void
    {
        $source = new MilitaryUnitRecipeSource($this->xml);
        $this->expectException(InvalidArgumentException::class);
        $source->recipesFor(0, []);
    }

    public function test_returns_regular_units_including_elite_soldier_when_elite_false(): void
    {
        $source = new MilitaryUnitRecipeSource($this->xml);
        $recipes = $source->recipesFor(0, ['elite' => false]);

        $this->assertCount(2, $recipes);
        $names = array_column($recipes, 'name');
        $this->assertContains('Recruit', $names);
        $this->assertContains('EliteSoldier', $names, 'EliteSoldier lacks isElite attribute and belongs to regular barracks (P-38)');
        $this->assertNotContains('Swordsman', $names);
        $this->assertNotContains('UnproduceableUnit', $names);
    }

    public function test_returns_elite_units_when_elite_true(): void
    {
        $source = new MilitaryUnitRecipeSource($this->xml);
        $recipes = $source->recipesFor(8, ['elite' => true]);

        $this->assertCount(1, $recipes);
        $this->assertSame('Swordsman', $recipes[0]['name']);
        $this->assertSame(300, $recipes[0]['duration_seconds']);
        $this->assertSame(15, $recipes[0]['instant_finish_cost']);
        $this->assertTrue($recipes[0]['stacks_supported']);
        $this->assertSame(25, $recipes[0]['max_amount_per_order']);
        $this->assertSame(200, $recipes[0]['max_stacks_per_order']);
    }
}
