<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Enums\ProductionRejectionReason;
use App\Support\Zone\ZoneSnapshot;

final readonly class ProductionOrderPolicy
{
    public function __construct(
        private ProductionCatalogInterface $catalog
    ) {}

    public function decide(
        ZoneSnapshot $snapshot,
        int $grid,
        int $expectedProductionType,
        string $recipeName,
        int $amount,
    ): ProductionDecision {
        // 1. Building with grid exists in snapshot
        $targetBuilding = null;
        foreach ($snapshot->buildings() as $b) {
            $bGrid = (int) ($b['buildingGrid'] ?? $b['grid'] ?? 0);
            if ($bGrid === $grid) {
                $targetBuilding = $b;
                break;
            }
        }

        if ($targetBuilding === null) {
            return ProductionDecision::reject(ProductionRejectionReason::BuildingNotFound);
        }

        $rawName = (string) ($targetBuilding['buildingName_string'] ?? $targetBuilding['buildingName'] ?? $targetBuilding['name'] ?? '');
        if ($rawName === '') {
            return ProductionDecision::reject(ProductionRejectionReason::NotAProducer);
        }

        // 2. Building is in catalog of producers
        $actualProductionType = $this->catalog->productionTypeFor($rawName);
        if ($actualProductionType === null || $actualProductionType < 0) {
            return ProductionDecision::reject(ProductionRejectionReason::NotAProducer);
        }

        // 3. productionType matches payload expectation
        if ($actualProductionType !== $expectedProductionType) {
            return ProductionDecision::reject(ProductionRejectionReason::ProductionTypeMismatch);
        }

        // 4. Building is not currently upgrading
        $upgradeInProgress = (bool) ($targetBuilding['upgradeIsInProgress'] ?? $targetBuilding['upgrade_in_progress'] ?? false);
        if ($upgradeInProgress) {
            return ProductionDecision::reject(ProductionRejectionReason::BuildingUpgrading);
        }

        // 5. Recipe exists in catalog for this productionType
        $recipe = $this->catalog->findRecipe($actualProductionType, $recipeName);
        if ($recipe === null) {
            return ProductionDecision::reject(ProductionRejectionReason::RecipeUnknown);
        }

        // 6. Level requirements (only for explicit lists / non-type-1 branches per OQ-1 / C-2)
        if ($actualProductionType !== 1) {
            $level = isset($targetBuilding['upgradeLevel'])
                ? (int) $targetBuilding['upgradeLevel']
                : (isset($targetBuilding['level']) ? (int) $targetBuilding['level'] : null);

            if ($level !== null) {
                if ($level < $recipe->requiresUpgradeLevelMin || $level > $recipe->requiresUpgradeLevelMax) {
                    return ProductionDecision::reject(ProductionRejectionReason::RecipeLevelLocked);
                }
            }
        }

        // 7. Queue data must be available in snapshot (null = unparseable / unavailable)
        if ($snapshot->productionQueues() === null) {
            return ProductionDecision::reject(ProductionRejectionReason::QueueDataUnavailable);
        }

        // 8. Resource check (if resources exist in snapshot and recipe has known costs)
        $resources = $snapshot->resources();
        if ($resources !== [] && $recipe->costsKnown && $recipe->costs !== []) {
            foreach ($recipe->costs as $cost) {
                $required = $cost['count'] * $amount;
                $resName = (string) $cost['resource'];
                $available = $resources[$resName] ?? 0;
                if ($available < $required) {
                    return ProductionDecision::reject(ProductionRejectionReason::InsufficientResources);
                }
            }
        }

        // 9. Allowed
        return ProductionDecision::allow($recipe, $actualProductionType);
    }
}
