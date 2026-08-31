<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Sources;

use App\Services\Game\Production\Sources\SkillPointRecipeSource;
use PHPUnit\Framework\TestCase;

final class SkillPointRecipeSourceTest extends TestCase
{
    private string $xml = <<<'XML'
<scienceSystem>
    <skillPoints>
        <skillPoint id="Manuscript" instantFinishCost="200" resetCost="50">
            <productionLevel amountProduced="0" productionTime="1800">
                <cost name="SimplePaper" count="250" />
                <cost name="Water" count="250" />
            </productionLevel>
            <productionLevel amountProduced="10" productionTime="3600">
                <cost name="SimplePaper" count="260" />
                <cost name="Water" count="260" />
            </productionLevel>
        </skillPoint>
        <skillPoint id="Tome" instantFinishCost="400" resetCost="100">
            <productionLevel amountProduced="0" productionTime="7200">
                <cost name="IntermediatePaper" count="200" />
                <cost name="Manuscript" count="1" />
            </productionLevel>
        </skillPoint>
        <skillPoint id="Codex" instantFinishCost="800" resetCost="200">
            <productionLevel amountProduced="0" productionTime="14400">
                <cost name="AdvancedPaper" count="150" />
                <cost name="Tome" count="1" />
            </productionLevel>
        </skillPoint>
    </skillPoints>
</scienceSystem>
XML;

    public function test_parses_three_skill_points_with_lower_bound_and_tiers(): void
    {
        $source = new SkillPointRecipeSource($this->xml);
        $recipes = $source->recipesFor(2);

        $this->assertCount(3, $recipes);

        $manuscript = $recipes[0];
        $this->assertSame('Manuscript', $manuscript['name']);
        $this->assertSame(1800, $manuscript['duration_seconds']);
        $this->assertSame(200, $manuscript['instant_finish_cost']);
        $this->assertTrue($manuscript['cost_is_lower_bound']);
        $this->assertFalse($manuscript['stacks_supported']);
        $this->assertSame(1, $manuscript['max_amount_per_order']);
        $this->assertSame(1, $manuscript['max_stacks_per_order']);
        $this->assertCount(2, $manuscript['cost_tiers']);

        // Check tier 0
        $this->assertSame(0, $manuscript['cost_tiers'][0]['threshold']);
        $this->assertSame([
            ['resource' => 'SimplePaper', 'count' => 250, 'is_population' => false],
            ['resource' => 'Water', 'count' => 250, 'is_population' => false],
        ], $manuscript['cost_tiers'][0]['costs']);

        // Check tier 1
        $this->assertSame(10, $manuscript['cost_tiers'][1]['threshold']);
        $this->assertSame([
            ['resource' => 'SimplePaper', 'count' => 260, 'is_population' => false],
            ['resource' => 'Water', 'count' => 260, 'is_population' => false],
        ], $manuscript['cost_tiers'][1]['costs']);
    }
}
