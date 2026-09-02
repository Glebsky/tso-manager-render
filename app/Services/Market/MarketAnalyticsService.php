<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Enums\MarketItemKind;
use App\Http\Resources\MarketOfferResource;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\Support\MarketPeriod;
use App\Services\Market\Support\PeriodResolver;
use App\Services\MarketCacheService;
use Carbon\Carbon;

/**
 * The two analytics use cases: the server overview and a single trade pair.
 *
 * This class only orchestrates; every piece of real work belongs to a
 * dedicated collaborator (offers, history, popular items, periods, naming).
 * That is what allowed the former 400-line controller method to shrink to a
 * handful of readable calls.
 */
final readonly class MarketAnalyticsService
{
    public function __construct(
        private MarketCacheService $cache,
        private MarketOfferQueryService $offers,
        private MarketHistoryAggregator $history,
        private PopularItemService $popularItems,
        private PeriodResolver $periods,
        private ResourceNameResolver $names,
    ) {}

    /**
     * Popular goods plus a page of the currently active offers.
     *
     * @return array<string, mixed>
     */
    public function overview(string $serverId, MarketPeriod $period, int $page, int $limit, ?MarketItemKind $kind = null): array
    {
        $params = ['period' => $period->key];
        if ($kind !== null) {
            $params['kind'] = $kind->value;
        }

        $cached = $this->cache->remember(
            $serverId,
            'analytics_overview',
            $params,
            (int) config('market.cache_ttl.analytics_overview'),
            function () use ($serverId, $period, $kind): array {
                $offers = MarketOfferResource::listFrom(
                    $this->offers->activeOffers($serverId, $kind),
                    $this->names,
                    $this->offers
                );

                return [
                    'server_id' => $serverId,
                    'popular' => $this->popularItems->popular($serverId, $period, $kind),
                    'all_active_offers' => $offers,
                    'total_active_count' => count($offers),
                ];
            }
        );

        $totalActive = (int) $cached['total_active_count'];
        $offset = max(0, ($page - 1) * $limit);
        $offers = array_slice($cached['all_active_offers'], $offset, $limit);

        return [
            'server_id' => $cached['server_id'],
            'popular' => $cached['popular'],
            'active_offers' => $this->withTimeLeft($offers),
            'total_active_count' => $totalActive,
            'page' => $page,
            'has_more' => ($offset + $limit) < $totalActive,
        ];
    }

    /**
     * Full analytics of one trade pair, including the mirrored direction.
     *
     * @return array<string, mixed>
     */
    public function pair(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): array
    {
        return $this->cache->remember(
            $serverId,
            'analytics_pair',
            ['item_id' => $itemId, 'target_item_id' => $targetItemId, 'period' => $period->key],
            (int) config('market.cache_ttl.analytics_pair'),
            function () use ($serverId, $itemId, $targetItemId, $period): array {
                $span = $this->history->span($serverId, $itemId, $targetItemId, $period);
                $resolved = $this->periods->refineGranularity($period, $span['min'], $span['max']);

                $mirroredHistory = $this->history->series($serverId, $targetItemId, $itemId, $resolved);
                $mirroredStats = $this->history->mirroredStats($serverId, $targetItemId, $itemId, $resolved);
                $pairData = $this->history->statsAndPeriodInfo($serverId, $itemId, $targetItemId, $resolved);

                return [
                    'server_id' => $serverId,
                    'popular' => $this->popularItems->popular($serverId, $period),
                    'stats' => $pairData['stats'],
                    'history' => $this->history->series($serverId, $itemId, $targetItemId, $resolved),
                    'active_info' => $this->offers->summarize(
                        $this->offers->activeOffersForPair($serverId, $itemId, $targetItemId)
                    ),
                    'period_info' => $pairData['period_info'],
                    'mirrored_stats' => $mirroredStats,
                    'mirrored_history' => $mirroredHistory === [] ? null : $mirroredHistory,
                ];
            }
        );
    }

    /**
     * The remaining lifetime of an offer is request-time state and therefore
     * computed outside of the cached payload.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    private function withTimeLeft(array $offers): array
    {
        $now = Carbon::now();

        foreach ($offers as $index => $offer) {
            if (! isset($offer['expires_at'])) {
                continue;
            }

            $secondsLeft = $now->diffInSeconds(Carbon::parse((string) $offer['expires_at']));
            $offers[$index]['time_left'] = max(0, $secondsLeft);
        }

        return $offers;
    }
}
