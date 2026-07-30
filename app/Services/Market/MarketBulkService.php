<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Http\Resources\MarketOfferResource;
use App\Models\MarketServerConnection;
use App\Services\Market\Contracts\ArbitrageFinder;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\Support\PeriodResolver;
use App\Services\MarketCacheService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the single "everything the SPA needs" payload.
 *
 * Every part of it is delegated to the same collaborators the granular
 * endpoints use, so the bulk endpoint can no longer drift away from
 * /market/goods, /market/analytics or /market/arbitrage - which is exactly
 * what happened while all of this was copy-pasted inside one controller.
 */
final class MarketBulkService
{
    public function __construct(
        private readonly MarketCacheService $cache,
        private readonly MarketSettingsService $settings,
        private readonly MarketCatalogService $catalog,
        private readonly PopularItemService $popularItems,
        private readonly MarketOfferQueryService $offers,
        private readonly MarketHistoryAggregator $history,
        private readonly ArbitrageFinder $arbitrage,
        private readonly PeriodResolver $periods,
        private readonly ResourceNameResolver $names,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $serverId): array
    {
        $intervalMinutes = $this->settings->syncIntervalMinutes();
        $nextSyncAt = $this->nextSyncAt($serverId, $intervalMinutes);
        $ttlSeconds = $this->cacheTtlSeconds($nextSyncAt, $intervalMinutes);

        $data = $this->cache->remember(
            $serverId,
            'bulk',
            [],
            $ttlSeconds,
            fn (): array => $this->build($serverId)
        );

        return [
            'server_id' => $serverId,
            'cache_ttl_seconds' => $ttlSeconds,
            'next_sync_at' => $nextSyncAt->toIso8601String(),
            'data_version' => $this->cache->dataVersion($serverId),
            'goods' => $data['goods'],
            'targets_map' => $data['targets_map'],
            'popular' => $data['popular'],
            'active_offers' => $data['active_offers'],
            'total_active_count' => $data['total_active_count'],
            'arbitrage' => $data['arbitrage'],
            'pairs' => $data['pairs'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $serverId): array
    {
        $goods = $this->catalog->distinctGoods($serverId);
        $activeOffers = MarketOfferResource::listFrom(
            $this->offers->activeOffers($serverId),
            $this->names,
            $this->offers
        );

        $popular = [];
        foreach ($this->periodKeys() as $periodKey) {
            $popular[$periodKey] = $this->popularItems->popular($serverId, $this->periods->resolve($periodKey));
        }

        return [
            'goods' => $goods,
            'targets_map' => $this->catalog->targetsMap($serverId, $goods),
            'popular' => $popular,
            'active_offers' => $activeOffers,
            'total_active_count' => count($activeOffers),
            'arbitrage' => $this->arbitrage->find($serverId),
            'pairs' => $this->pairs($serverId),
        ];
    }

    /**
     * Per-pair statistics, series and live info for every configured period.
     *
     * @return array<string, array<string, mixed>>
     */
    private function pairs(string $serverId): array
    {
        $pairs = [];
        $periodKeys = $this->periodKeys();

        foreach ($periodKeys as $periodKey) {
            $period = $this->periods->resolve($periodKey);

            if (! $period->isBounded()) {
                continue;
            }

            /** @var CarbonInterface $since */
            $since = $period->since;
            $seriesByPair = $this->history->seriesByPair($serverId, $since, $period->granularity);

            foreach ($this->history->statsByPair($serverId, $since) as $stat) {
                $pairKey = $this->history->pairKey((string) $stat->item_id, (string) $stat->target_item_id);

                if (! isset($pairs[$pairKey])) {
                    $pairs[$pairKey] = [];
                }

                $pairs[$pairKey][$periodKey] = [
                    'stats' => [
                        'average' => round((float) ($stat->average_price ?? 0), 2),
                        'minimum' => round((float) ($stat->min_price ?? 0), 2),
                        'maximum' => round((float) ($stat->max_price ?? 0), 2),
                        'current' => 0,
                    ],
                    'history' => $seriesByPair[$pairKey] ?? [],
                    'period_info' => [
                        'volume' => (int) ($stat->total_volume ?? 0),
                        'offers_count' => (int) ($stat->offers_count ?? 0),
                        'sellers_count' => (int) ($stat->sellers_count ?? 0),
                    ],
                ];
            }
        }

        foreach ($this->history->latestPricesByPair($serverId) as $pairKey => $price) {
            foreach ($periodKeys as $periodKey) {
                if (isset($pairs[$pairKey][$periodKey])) {
                    $pairs[$pairKey][$periodKey]['stats']['current'] = $price;
                }
            }
        }

        foreach ($this->offers->activeInfoByPair($serverId) as $active) {
            $pairKey = $this->history->pairKey((string) $active->item_id, (string) $active->target_item_id);

            if (isset($pairs[$pairKey])) {
                $pairs[$pairKey]['active_info'] = [
                    'volume' => (int) $active->volume,
                    'offers_count' => (int) $active->offers_count,
                    'sellers_count' => (int) $active->sellers_count,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function periodKeys(): array
    {
        /** @var list<string> $keys */
        $keys = (array) config('market.bulk.periods', ['1d', '7d']);

        return array_values($keys);
    }

    private function nextSyncAt(string $serverId, int $intervalMinutes): CarbonInterface
    {
        $lastSyncedAt = MarketServerConnection::where('server_id', $serverId)->first()?->last_synced_at;

        return $lastSyncedAt
            ? $lastSyncedAt->copy()->addMinutes($intervalMinutes)
            : Carbon::now()->addMinutes($intervalMinutes);
    }

    /**
     * Cache exactly until the next expected sync, never shorter than the
     * configured floor and never longer than one sync interval.
     */
    private function cacheTtlSeconds(CarbonInterface $nextSyncAt, int $intervalMinutes): int
    {
        $minimum = (int) config('market.bulk.min_ttl_seconds', 30);
        $ttl = (int) max($minimum, Carbon::now()->diffInSeconds($nextSyncAt, false));

        return min($ttl, $intervalMinutes * 60);
    }
}
