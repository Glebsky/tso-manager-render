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
        int $amount = 1,
        int $stacks = 1,
    ): ProductionDecision {
        // Step 1: Building with grid exists in snapshot
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

        // Step 2: Building is in catalog of producers
        $actualProductionType = $this->catalog->productionTypeFor($rawName);
        if ($actualProductionType === null || $actualProductionType < 0) {
            return ProductionDecision::reject(ProductionRejectionReason::NotAProducer);
        }

        // Step 3: Production type is supported (not unsupported:*)
        if (! $this->catalog->isTypeSupported($actualProductionType)) {
            return ProductionDecision::reject(ProductionRejectionReason::ProductionTypeUnsupported);
        }

        // Step 4: productionType matches payload expectation
        if ($actualProductionType !== $expectedProductionType) {
            return ProductionDecision::reject(ProductionRejectionReason::ProductionTypeMismatch);
        }

        // Step 5: Recipe exists in catalog for this productionType
        $recipe = $this->catalog->findRecipe($actualProductionType, $recipeName);
        if ($recipe === null) {
            return ProductionDecision::reject(ProductionRejectionReason::RecipeUnknown);
        }

        // Step 6: Amount within recipe limit
        if ($amount > $recipe->maxAmountPerOrder || $amount < 1) {
            return ProductionDecision::reject(ProductionRejectionReason::AmountExceedsRecipeLimit);
        }

        // Step 7: Stacks within recipe limit
        if ($stacks > $recipe->maxStacksPerOrder || $stacks < 1) {
            return ProductionDecision::reject(ProductionRejectionReason::StacksExceedsRecipeLimit);
        }

        // Step 8: Level requirements (for non-type-1 producers)
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

        // Step 9: Event active check
        if ($recipe->requiresEvent !== null && $recipe->requiresEvent !== '') {
            $activeEvents = $snapshot->activeEvents();
            if ($activeEvents !== null && ! in_array($recipe->requiresEvent, $activeEvents, true)) {
                return ProductionDecision::reject(ProductionRejectionReason::RecipeRequiresInactiveEvent);
            }
        }

        // Step 10: Building is not currently upgrading
        $upgradeInProgress = (bool) ($targetBuilding['upgradeIsInProgress'] ?? $targetBuilding['upgrade_in_progress'] ?? false);
        if ($upgradeInProgress) {
            return ProductionDecision::reject(ProductionRejectionReason::BuildingUpgrading);
        }

        // Step 11: Queue data & resources check
        if ($snapshot->productionQueues() === null) {
            return ProductionDecision::reject(ProductionRejectionReason::QueueDataUnavailable);
        }

        $queueState = $snapshot->productionQueueFor($actualProductionType);
        if ($queueState !== null && $queueState->isFull()) {
            return ProductionDecision::reject(ProductionRejectionReason::QueueFull);
        }

        // Resource check (ignoring Population)
        $resources = $snapshot->resources();
        if ($resources !== [] && $recipe->costsKnown && $recipe->costs !== []) {
            foreach ($recipe->costs as $cost) {
                $isPopulation = (bool) ($cost['is_population'] ?? ($cost['resource'] === 'Population'));
                if ($isPopulation) {
                    continue;
                }

                $required = $cost['count'] * $amount * $stacks;
                $resName = (string) $cost['resource'];
                $available = $resources[$resName] ?? 0;
                if ($available < $required) {
                    return ProductionDecision::reject(ProductionRejectionReason::InsufficientResources);
                }
            }
        }

        return ProductionDecision::allow($recipe, $actualProductionType);
    }
}
