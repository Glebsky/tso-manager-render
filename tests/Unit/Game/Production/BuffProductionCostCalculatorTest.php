<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Services\Game\Production\BuffProductionCostCalculator;
use App\Services\Game\Production\ProductionRecipe;
use PHPUnit\Framework\TestCase;

final class BuffProductionCostCalculatorTest extends TestCase
{
    private BuffProductionCostCalculator $calculator;

    private ProductionRecipe $buffLvl1;

    private ProductionRecipe $buffLvl3;

    private ProductionRecipe $freeBuff;

    private ProductionRecipe $militaryUnit;

    private ProductionRecipe $skillPoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new BuffProductionCostCalculator;

        $this->buffLvl1 = new ProductionRecipe(
            name: 'ProductivityBuffLvl1',
            group: 0,
            durationSeconds: 60,
            buffType: 'Timed',
            requiresUpgradeLevelMin: 0,
            requiresUpgradeLevelMax: 99,
            requiresEvent: null,
            requiresQuest: null,
            costs: [
                ['resource' => 'Fish', 'count' => 10],
            ],
            costsKnown: true,
        );

        $this->buffLvl3 = new ProductionRecipe(
            name: 'ProductivityBuffLvl3',
            group: 0,
            durationSeconds: 1800,
            buffType: 'Timed',
            requiresUpgradeLevelMin: 0,
            requiresUpgradeLevelMax: 99,
            requiresEvent: null,
            requiresQuest: null,
            costs: [
                ['resource' => 'Bread', 'count' => 60],
                ['resource' => 'Fish', 'count' => 120],
                ['resource' => 'Sausage', 'count' => 20],
            ],
            costsKnown: true,
        );

        $this->freeBuff = new ProductionRecipe(
            name: 'FreeBuff',
            group: 0,
            durationSeconds: 120,
            buffType: 'Instant',
            requiresUpgradeLevelMin: 0,
            requiresUpgradeLevelMax: 99,
            requiresEvent: null,
            requiresQuest: null,
            costs: [],
            costsKnown: false,
        );

        $this->militaryUnit = new ProductionRecipe(
            name: 'Recruit',
            group: 0,
            durationSeconds: 180,
            buffType: 'Timed',
            requiresUpgradeLevelMin: 0,
            requiresUpgradeLevelMax: 99,
            requiresEvent: null,
            requiresQuest: null,
            costs: [
                ['resource' => 'Beer', 'count' => 5, 'is_population' => false],
                ['resource' => 'BronzeSword', 'count' => 10, 'is_population' => false],
                ['resource' => 'Population', 'count' => 1, 'is_population' => true],
            ],
            costsKnown: true,
        );

        $this->skillPoint = new ProductionRecipe(
            name: 'Manuscript',
            group: 0,
            durationSeconds: 1800,
            buffType: 'Timed',
            requiresUpgradeLevelMin: 0,
            requiresUpgradeLevelMax: 99,
            requiresEvent: null,
            requiresQuest: null,
            costs: [
                ['resource' => 'SimplePaper', 'count' => 250, 'is_population' => false],
                ['resource' => 'Water', 'count' => 250, 'is_population' => false],
            ],
            costsKnown: true,
            costIsLowerBound: true,
        );
    }

    public function test_for_order_single_amount(): void
    {
        $cost1 = $this->calculator->forOrder($this->buffLvl1, amount: 1, stacks: 1);
        $this->assertSame(['Fish' => 10], $cost1->resources);
        $this->assertSame(60, $cost1->durationSeconds);
        $this->assertTrue($cost1->complete);
        $this->assertSame(0, $cost1->population);
        $this->assertFalse($cost1->isLowerBound);

        $cost3 = $this->calculator->forOrder($this->buffLvl3, amount: 1, stacks: 1);
        $this->assertSame(['Bread' => 60, 'Fish' => 120, 'Sausage' => 20], $cost3->resources);
        $this->assertSame(1800, $cost3->durationSeconds);
        $this->assertTrue($cost3->complete);
    }

    public function test_for_order_multiplied_by_amount_and_stacks(): void
    {
        $cost = $this->calculator->forOrder($this->buffLvl3, amount: 5, stacks: 4);
        // Total factor = 20
        $this->assertSame(['Bread' => 1200, 'Fish' => 2400, 'Sausage' => 400], $cost->resources);
        $this->assertSame(36000, $cost->durationSeconds);
        $this->assertTrue($cost->complete);
    }

    public function test_for_order_separates_population_from_resources(): void
    {
        $cost = $this->calculator->forOrder($this->militaryUnit, amount: 10, stacks: 2);
        // Total factor = 20
        $this->assertSame(['Beer' => 100, 'BronzeSword' => 200], $cost->resources);
        $this->assertArrayNotHasKey('Population', $cost->resources);
        $this->assertSame(20, $cost->population);
        $this->assertSame(3600, $cost->durationSeconds);
    }

    public function test_for_order_with_lower_bound_cost(): void
    {
        $cost = $this->calculator->forOrder($this->skillPoint, amount: 1, stacks: 1);
        $this->assertSame(['SimplePaper' => 250, 'Water' => 250], $cost->resources);
        $this->assertTrue($cost->isLowerBound);
    }

    public function test_for_order_with_unknown_costs(): void
    {
        $cost = $this->calculator->forOrder($this->freeBuff, amount: 2, stacks: 1);
        $this->assertSame([], $cost->resources);
        $this->assertSame(240, $cost->durationSeconds);
        $this->assertFalse($cost->complete);
    }

    public function test_for_sequence_aggregates_overlapping_resources_and_population(): void
    {
        $orders = [
            ['recipe' => $this->buffLvl1, 'amount' => 2, 'stacks' => 1], // Fish 20, dur 120, pop 0
            ['recipe' => $this->militaryUnit, 'amount' => 5, 'stacks' => 1], // Beer 25, BronzeSword 50, pop 5, dur 900
            ['recipe' => $this->skillPoint, 'amount' => 1, 'stacks' => 1], // SimplePaper 250, Water 250, pop 0, isLowerBound true
        ];

        $total = $this->calculator->forSequence($orders);
        $this->assertSame([
            'Beer' => 25,
            'BronzeSword' => 50,
            'Fish' => 20,
            'SimplePaper' => 250,
            'Water' => 250,
        ], $total->resources);
        $this->assertSame(5, $total->population);
        $this->assertSame(2820, $total->durationSeconds);
        $this->assertTrue($total->complete);
        $this->assertTrue($total->isLowerBound);
    }
}
