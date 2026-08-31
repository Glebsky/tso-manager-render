<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Services\Game\Mines\BuildQueueBudget;
use App\Services\Game\Mines\BuildQueueSnapshot;
use PHPUnit\Framework\TestCase;

final class BuildQueueBudgetTest extends TestCase
{
    public function test_it_reports_free_slots(): void
    {
        $budget = new BuildQueueBudget(new BuildQueueSnapshot(used: 2, total: 5));
        $this->assertSame(3, $budget->freeSlots());
        $this->assertTrue($budget->hasFreeSlot());
    }

    public function test_it_clamps_negative_budget_to_zero(): void
    {
        $budget = new BuildQueueBudget(new BuildQueueSnapshot(used: 6, total: 5));
        $this->assertSame(0, $budget->freeSlots());
        $this->assertFalse($budget->hasFreeSlot());
    }

    public function test_it_treats_missing_queue_data_as_no_free_slots(): void
    {
        $budget = new BuildQueueBudget(null);
        $this->assertSame(0, $budget->freeSlots());
        $this->assertFalse($budget->hasFreeSlot());
    }
}
