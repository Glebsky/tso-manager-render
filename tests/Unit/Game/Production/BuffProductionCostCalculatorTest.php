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
    }

    public function test_for_order_single_amount(): void
    {
        $cost1 = $this->calculator->forOrder($this->buffLvl1, amount: 1, stacks: 1);
        $this->assertSame(['Fish' => 10], $cost1->resources);
        $this->assertSame(60, $cost1->durationSeconds);
        $this->assertTrue($cost1->complete);

        $cost3 = $this->calculator->forOrder($this->buffLvl3, amount: 1, stacks: 1);
        $this->assertSame(['Bread' => 60, 'Fish' => 120, 'Sausage' => 20], $cost3->resources);
        $this->assertSame(1800, $cost3->durationSeconds);
        $this->assertTrue($cost3->complete);
    }

    public function test_for_order_multiplied_by_amount(): void
    {
        $cost = $this->calculator->forOrder($this->buffLvl3, amount: 3, stacks: 1);
        $this->assertSame(['Bread' => 180, 'Fish' => 360, 'Sausage' => 60], $cost->resources);
        $this->assertSame(5400, $cost->durationSeconds);
        $this->assertTrue($cost->complete);
    }

    public function test_for_order_with_unknown_costs(): void
    {
        $cost = $this->calculator->forOrder($this->freeBuff, amount: 2, stacks: 1);
        $this->assertSame([], $cost->resources);
        $this->assertSame(240, $cost->durationSeconds);
        $this->assertFalse($cost->complete);
    }

    public function test_for_sequence_aggregates_overlapping_resources_and_collapses_complete_flag(): void
    {
        $orders = [
            ['recipe' => $this->buffLvl1, 'amount' => 2, 'stacks' => 1], // Fish 20, dur 120
            ['recipe' => $this->buffLvl3, 'amount' => 1, 'stacks' => 1], // Fish 120, Bread 60, Sausage 20, dur 1800
        ];

        $total = $this->calculator->forSequence($orders);
        $this->assertSame(['Bread' => 60, 'Fish' => 140, 'Sausage' => 20], $total->resources);
        $this->assertSame(1920, $total->durationSeconds);
        $this->assertTrue($total->complete);

        // Sequence with one unknown cost recipe
        $ordersWithIncomplete = [
            ['recipe' => $this->buffLvl1, 'amount' => 1, 'stacks' => 1],
            ['recipe' => $this->freeBuff, 'amount' => 1, 'stacks' => 1],
        ];

        $totalIncomplete = $this->calculator->forSequence($ordersWithIncomplete);
        $this->assertSame(['Fish' => 10], $totalIncomplete->resources);
        $this->assertSame(180, $totalIncomplete->durationSeconds);
        $this->assertFalse($totalIncomplete->complete);
    }
}
