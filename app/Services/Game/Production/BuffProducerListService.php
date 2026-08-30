<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;

final readonly class BuffProducerListService
{
    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private ProductionCatalogInterface $catalog
    ) {}

    public static function clearCache(int $accountId): void
    {
        Cache::forget("tso:producers:{$accountId}");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forAccount(Account $account, bool $forceRefresh = false): array
    {
        $cacheKey = "tso:producers:{$account->id}";

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                /** @var list<array<string, mixed>> $cached */
                return $cached;
            }
        }

        $snapshot = $forceRefresh ? $this->zones->forAccount($account, true) : $this->zones->forAccount($account);
        $producerBuildings = $snapshot->producerBuildings($this->catalog);

        $producers = [];
        foreach ($producerBuildings as $b) {
            $grid = (int) $b['grid'];
            $buildingName = (string) $b['building_name'];
            $productionType = (int) $b['production_type'];
            $upgradeLevel = $b['upgrade_level'];
            $upgradeInProgress = (bool) $b['upgrade_in_progress'];
            $productionActive = (bool) $b['production_active'];

            $queueState = $snapshot->productionQueueFor($productionType);
            $queueData = [
                'used' => $queueState !== null ? $queueState->used() : 0,
                'orders' => $queueState !== null ? $queueState->orders : [],
            ];

            $recipes = [];
            foreach ($this->catalog->recipesFor($productionType) as $recipe) {
                $isLocked = false;
                if ($upgradeLevel !== null && $productionType !== 1) {
                    if ($upgradeLevel < $recipe->requiresUpgradeLevelMin || $upgradeLevel > $recipe->requiresUpgradeLevelMax) {
                        $isLocked = true;
                    }
                }

                $recipes[] = [
                    'name' => $recipe->name,
                    'group' => $recipe->group,
                    'duration_seconds' => $recipe->durationSeconds,
                    'buff_type' => $recipe->buffType,
                    'requires_upgrade_level_min' => $recipe->requiresUpgradeLevelMin,
                    'requires_upgrade_level_max' => $recipe->requiresUpgradeLevelMax,
                    'is_locked' => $isLocked,
                    'costs' => $recipe->costs,
                    'costs_known' => $recipe->costsKnown,
                ];
            }

            $producers[] = [
                'grid' => $grid,
                'building_name' => $buildingName,
                'production_type' => $productionType,
                'upgrade_level' => $upgradeLevel,
                'upgrade_in_progress' => $upgradeInProgress,
                'production_active' => $productionActive,
                'queue' => $queueData,
                'recipes' => $recipes,
            ];
        }

        Cache::put($cacheKey, $producers, 60);

        return $producers;
    }
}
