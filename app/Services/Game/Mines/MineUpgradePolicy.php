<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Enums\UpgradeRejectionReason;
use App\Http\Requests\Tasks\ScheduledTaskRequest;
use App\Services\Game\Mines\Contracts\MineCatalogInterface;

final readonly class MineUpgradePolicy
{
    /**
     * Note: If changed, synchronize with ScheduledTaskRequest rules (max:7).
     *
     * @see ScheduledTaskRequest
     */
    public const int DEFAULT_MAX_LEVEL = 7;

    public function __construct(
        private MineCatalogInterface $catalog,
    ) {}

    public function decide(ZoneSnapshot $zone, int $grid, ?int $requestedMaxLevel = null): UpgradeDecision
    {
        $freeSlots = $zone->buildQueueBudget()->freeSlots();

        $building = $zone->buildingAt($grid);
        if ($building === null) {
            return UpgradeDecision::reject(UpgradeRejectionReason::NoBuildingAtGrid, $grid, 0, null, 0, '', $freeSlots);
        }

        if (str_starts_with($building->name, 'MineDepletedDeposit')) {
            return UpgradeDecision::reject(UpgradeRejectionReason::MineDepleted, $grid, 0, null, 0, $building->name, $freeSlots);
        }

        $definition = $this->catalog->findByBuilding($building->name);
        if ($definition === null) {
            return UpgradeDecision::reject(UpgradeRejectionReason::NotAMine, $grid, $building->upgradeLevel, null, 0, $building->name, $freeSlots);
        }

        if ($building->upgradeLevel <= 0) {
            return UpgradeDecision::reject(UpgradeRejectionReason::BuildingUnderConstruction, $grid, $building->upgradeLevel, $definition, 0, $building->name, $freeSlots);
        }

        $deposit = $zone->depositAt($grid);
        if ($deposit !== null && ($deposit->isDepleted() || ! $deposit->isAccessible())) {
            return UpgradeDecision::reject(UpgradeRejectionReason::MineDepleted, $grid, $building->upgradeLevel, $definition, 0, $building->name, $freeSlots);
        }

        $requested = ($requestedMaxLevel !== null && $requestedMaxLevel > 0) ? $requestedMaxLevel : $definition->maxUpgradeLevel;
        $target = min($requested, $definition->maxUpgradeLevel);

        if ($building->upgradeLevel >= $target) {
            return UpgradeDecision::reject(UpgradeRejectionReason::MaxLevelReached, $grid, $building->upgradeLevel, $definition, $target, $building->name, $freeSlots);
        }

        if ($building->upgradeInProgress) {
            return UpgradeDecision::reject(UpgradeRejectionReason::UpgradeAlreadyInProgress, $grid, $building->upgradeLevel, $definition, $target, $building->name, $freeSlots);
        }

        if (! $building->isProductionActive) {
            return UpgradeDecision::reject(UpgradeRejectionReason::ProductionInactive, $grid, $building->upgradeLevel, $definition, $target, $building->name, $freeSlots);
        }

        if (! $zone->buildQueueBudget()->hasFreeSlot()) {
            return UpgradeDecision::reject(UpgradeRejectionReason::BuildQueueFull, $grid, $building->upgradeLevel, $definition, $target, $building->name, $freeSlots);
        }

        return UpgradeDecision::allow($definition, $grid, $building->upgradeLevel, $building->upgradeLevel + 1, $freeSlots);
    }
}
