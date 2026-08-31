<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Sources;

use App\Services\Game\Production\Sources\Combat3UnitRecipeSource;
use PHPUnit\Framework\TestCase;

final class Combat3UnitRecipeSourceTest extends TestCase
{
    private string $xml = <<<'XML'
<GameUnit>
    <Unit Type="ExpeditionCuirassier" Tier="3" group="AttackUnits">
        <Properties>
            <Property Type="IsProducible" Value="1" />
            <Property Type="ProductionTime" Value="360" />
            <Property Type="InstantBuildCost" Value="10" />
        </Properties>
        <Costs>
            <Cost Type="Population" Amount="1" />
            <Cost Type="Beer" Amount="20" />
        </Costs>
    </Unit>
    <Unit Type="ExpeditionPikeman" Tier="3" group="AttackUnits">
        <Properties>
            <Property Type="IsProducible" Value="1" />
            <Property Type="ProductionTime" Value="360" />
            <Property Type="InstantBuildCost" Value="2" />
        </Properties>
        <Costs>
            <Cost Type="Population" Amount="1" />
            <Cost Type="Beer" Amount="10" />
        </Costs>
    </Unit>
    <Unit Type="ExpeditionTank" Tier="3" group="TankUnits">
        <Properties>
            <Property Type="IsProducible" Value="1" />
            <Property Type="ProductionTime" Value="360" />
            <Property Type="InstantBuildCost" Value="10" />
        </Properties>
        <Costs>
            <Cost Type="Population" Amount="1" />
            <Cost Type="ValorPoint" Amount="4" />
        </Costs>
    </Unit>
    <Unit Type="EnemyBoss" Tier="3">
        <Properties>
            <Property Type="IsProducible" Value="0" />
            <Property Type="ProductionTime" Value="360" />
        </Properties>
        <Costs />
    </Unit>
</GameUnit>
XML;

    public function test_parses_producible_combat3_units_with_properties(): void
    {
        $source = new Combat3UnitRecipeSource($this->xml);
        $recipes = $source->recipesFor(7);

        $this->assertCount(3, $recipes);

        $cuirassier = $recipes[0];
        $this->assertSame('ExpeditionCuirassier', $cuirassier['name']);
        $this->assertSame(3, $cuirassier['tier']);
        $this->assertSame('AttackUnits', $cuirassier['unit_group']);
        $this->assertSame(360, $cuirassier['duration_seconds']);
        $this->assertSame(10, $cuirassier['instant_finish_cost']);
        $this->assertTrue($cuirassier['unverified_protocol']);
        $this->assertTrue($cuirassier['stacks_supported']);
        $this->assertSame(25, $cuirassier['max_amount_per_order']);
        $this->assertSame(200, $cuirassier['max_stacks_per_order']);

        $pikeman = $recipes[1];
        $this->assertSame('ExpeditionPikeman', $pikeman['name']);
        $this->assertSame(2, $pikeman['instant_finish_cost']);

        $tank = $recipes[2];
        $this->assertSame('ExpeditionTank', $tank['name']);
        $this->assertSame('TankUnits', $tank['unit_group']);
        $this->assertSame([
            ['resource' => 'Population', 'count' => 1, 'is_population' => true],
            ['resource' => 'ValorPoint', 'count' => 4, 'is_population' => false],
        ], $tank['costs']);
    }
}
