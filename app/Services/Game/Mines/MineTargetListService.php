<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Models\Account;
use App\Services\Game\Mines\Contracts\MineCatalogInterface;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use Illuminate\Support\Facades\Cache;

final readonly class MineTargetListService
{
    public const int CACHE_TTL_SECONDS = 30;

    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private MineCatalogInterface $catalog,
        private MinePlacementPolicy $placementPolicy,
        private MineUpgradePolicy $upgradePolicy,
    ) {}

    public static function buildableCacheKey(int $accountId): string
    {
        return "buildable_deposits_{$accountId}";
    }

    public static function upgradableCacheKey(int $accountId): string
    {
        return "upgradable_mines_{$accountId}";
    }

    public static function clearCache(int $accountId): void
    {
        Cache::forget(self::buildableCacheKey($accountId));
        Cache::forget(self::upgradableCacheKey($accountId));
    }

    /**
     * @return list<array{grid: int, deposit_name: string, mine_name: string, amount: int, max_amount: int, allowed: bool, reason: string}>
     */
    public function buildableDeposits(Account $account, bool $skipCache = false): array
    {
        $cacheKey = self::buildableCacheKey((int) $account->id);

        if (! $skipCache && Cache::has($cacheKey)) {
            /** @var list<array{grid: int, deposit_name: string, mine_name: string, amount: int, max_amount: int, allowed: bool, reason: string}> $cached */
            $cached = (array) Cache::get($cacheKey, []);

            return $cached;
        }

        $zone = $this->zones->forAccount($account);
        $result = [];

        foreach ($zone->deposits() as $deposit) {
            $def = $this->catalog->findByDeposit($deposit->name);
            if ($def === null) {
                continue;
            }

            $decision = $this->placementPolicy->decide($zone, $deposit->grid);

            $result[] = [
                'grid' => $deposit->grid,
                'deposit_name' => $deposit->name,
                'mine_name' => $def->buildingName,
                'amount' => $deposit->amount,
                'max_amount' => $deposit->maxAmount,
                'allowed' => $decision->allowed,
                'reason' => $decision->reason->value,
            ];
        }

        Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    /**
     * @return list<array{grid: int, building_name: string, deposit_name: string, level: int, max_level: int, is_active: bool, upgrade_in_progress: bool, allowed: bool, reason: string}>
     */
    public function upgradableMines(Account $account, bool $skipCache = false): array
    {
        $cacheKey = self::upgradableCacheKey((int) $account->id);

        if (! $skipCache && Cache::has($cacheKey)) {
            /** @var list<array{grid: int, building_name: string, deposit_name: string, level: int, max_level: int, is_active: bool, upgrade_in_progress: bool, allowed: bool, reason: string}> $cached */
            $cached = (array) Cache::get($cacheKey, []);

            return $cached;
        }

        $zone = $this->zones->forAccount($account);
        $result = [];

        foreach ($zone->buildings() as $building) {
            $def = $this->catalog->findByBuilding($building->name);
            if ($def === null) {
                continue;
            }

            $decision = $this->upgradePolicy->decide($zone, $building->grid);

            $result[] = [
                'grid' => $building->grid,
                'building_name' => $building->name,
                'deposit_name' => $def->depositName,
                'level' => $building->upgradeLevel,
                'max_level' => $def->maxUpgradeLevel,
                'is_active' => $building->isProductionActive,
                'upgrade_in_progress' => $building->upgradeInProgress,
                'allowed' => $decision->allowed,
                'reason' => $decision->reason->value,
            ];
        }

        Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }
}
