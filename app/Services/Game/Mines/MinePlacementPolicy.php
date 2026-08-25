<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Enums\PlacementRejectionReason;
use App\Services\Game\Mines\Contracts\MineCatalogInterface;

final readonly class MinePlacementPolicy
{
    public function __construct(
        private MineCatalogInterface $catalog,
    ) {}

    public function decide(ZoneSnapshot $zone, int $grid): PlacementDecision
    {
        $freeSlots = $zone->buildQueueBudget()->freeSlots();

        $deposit = $zone->depositAt($grid);
        if ($deposit === null) {
            return PlacementDecision::reject(PlacementRejectionReason::NoDepositAtGrid, $grid, null, '', $freeSlots);
        }

        $definition = $this->catalog->findByDeposit($deposit->name);
        if ($definition === null) {
            return PlacementDecision::reject(PlacementRejectionReason::UnknownDepositType, $grid, null, $deposit->name, $freeSlots);
        }

        if ($deposit->isDepleted()) {
            return PlacementDecision::reject(PlacementRejectionReason::DepositEmpty, $grid, $definition, $deposit->name, $freeSlots);
        }

        if ($zone->hasBuildingAt($grid)) {
            return PlacementDecision::reject(PlacementRejectionReason::GridOccupied, $grid, $definition, $deposit->name, $freeSlots);
        }

        if (! $zone->buildQueueBudget()->hasFreeSlot()) {
            return PlacementDecision::reject(PlacementRejectionReason::BuildQueueFull, $grid, $definition, $deposit->name, $freeSlots);
        }

        return PlacementDecision::allow($definition, $grid, $freeSlots);
    }
}
