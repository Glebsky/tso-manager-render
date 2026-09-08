<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\GameErrorResolver;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Cache;

final readonly class AmfZoneSnapshotProvider implements ZoneSnapshotProviderInterface
{
    public const int ZONE_CACHE_TTL_SECONDS = 60;

    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $zones,
    ) {}

    public static function cacheKey(int $accountId): string
    {
        return "tso:mine_zone:{$accountId}";
    }

    public static function clearCache(int $accountId): void
    {
        Cache::forget(self::cacheKey($accountId));
    }

    /**
     * @throws GameServerErrorException
     * @throws \Exception
     */
    public function forAccount(Account $account, bool $forceRefresh = false): ZoneSnapshot
    {
        $cacheKey = self::cacheKey($account->id);
        $zone = null;

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['buildings'])) {
                $zone = $cached;
            } elseif (! empty($account->zone_data) && ! empty($account->zone_data['buildings']) && $account->last_sync_at !== null && $account->last_sync_at->diffInSeconds(now()) < self::ZONE_CACHE_TTL_SECONDS) {
                $zone = $account->zone_data;
                Cache::put($cacheKey, $zone, self::ZONE_CACHE_TTL_SECONDS);
            }
        }

        if ($zone === null) {
            $zoneAmf = $this->amf->getZone($account);
            $zone = $this->zones->parse($zoneAmf);

            $errorCode = (int) ($zone['errorCode'] ?? 0);
            if ($errorCode !== 0) {
                throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
            }

            Cache::put($cacheKey, $zone, self::ZONE_CACHE_TTL_SECONDS);

            if ($account->exists) {
                $existingFriends = is_array($account->zone_data) && is_array($account->zone_data['friends'] ?? null)
                    ? $account->zone_data['friends']
                    : [];
                if (! empty($existingFriends)) {
                    $zone['friends'] = $existingFriends;
                }

                $account->update([
                    'zone_data' => json_encode($zone, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'last_sync_at' => now(),
                    'status' => 'online',
                ]);
            }
        }

        $depositsByGrid = [];
        if (isset($zone['deposits']) && is_array($zone['deposits'])) {
            foreach ($zone['deposits'] as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $grid = (int) ($d['grid'] ?? 0);
                if ($grid <= 0) {
                    continue;
                }

                $depositsByGrid[$grid] = new DepositSnapshot(
                    grid: $grid,
                    name: (string) ($d['name'] ?? ''),
                    amount: (int) ($d['amount'] ?? 0),
                    maxAmount: (int) ($d['max_amount'] ?? 0),
                    accessible: isset($d['accessible']) ? (int) $d['accessible'] : null,
                );
            }
        }

        $buildingsByGrid = [];
        if (isset($zone['buildings']) && is_array($zone['buildings'])) {
            foreach ($zone['buildings'] as $b) {
                if (! is_array($b)) {
                    continue;
                }
                $grid = (int) ($b['buildingGrid'] ?? $b['grid'] ?? 0);
                if ($grid <= 0) {
                    continue;
                }

                $name = (string) ($b['buildingName_string'] ?? $b['buildingName'] ?? '');
                $level = (int) ($b['upgradeLevel'] ?? $b['level'] ?? 0);
                $isProductionActive = (bool) ($b['isProductionActive'] ?? false);
                $upgradeInProgress = (bool) ($b['upgradeIsInProgress'] ?? false);

                $buildingsByGrid[$grid] = new BuildingSnapshot(
                    grid: $grid,
                    name: $name,
                    upgradeLevel: $level,
                    isProductionActive: $isProductionActive,
                    upgradeInProgress: $upgradeInProgress,
                );
            }
        }

        $buildQueue = null;
        if (isset($zone['build_queue']) && is_array($zone['build_queue'])) {
            $buildQueue = new BuildQueueSnapshot(
                used: (int) ($zone['build_queue']['used'] ?? 0),
                total: (int) ($zone['build_queue']['total'] ?? 0),
            );
        }

        return new ZoneSnapshot(
            depositsByGrid: $depositsByGrid,
            buildingsByGrid: $buildingsByGrid,
            buildQueue: $buildQueue,
        );
    }
}
