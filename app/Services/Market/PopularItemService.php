<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Models\MarketHistory;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\Support\MarketPeriod;
use App\Services\MarketCacheService;

/**
 * Most traded goods of a server within a period.
 */
final class PopularItemService
{
    public function __construct(
        private readonly ResourceNameResolver $names,
        private readonly MarketCacheService $cache,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function popular(string $serverId, MarketPeriod $period): array
    {
        return $this->cache->remember(
            $serverId,
            'popular',
            ['period' => $period->key],
            (int) config('market.cache_ttl.popular'),
            function () use ($serverId, $period): array {
                $query = MarketHistory::query()
                    ->where('server_id', $serverId)
                    ->whereNotNull('item_id')
                    ->where('item_id', '!=', '')
                    ->selectRaw('item_id, item_name, count(*) as offers_count, count(distinct player_id) as sellers_count, sum(volume) as total_volume');

                if ($period->isBounded()) {
                    $query->where('collected_at', '>=', $period->since);
                }

                return $query->groupBy('item_id', 'item_name')
                    ->orderByDesc('offers_count')
                    ->orderByDesc('total_volume')
                    ->limit((int) config('market.popular_items_limit'))
                    ->get()
                    ->map(function ($row) {
                        $row->item_name = $this->names->resolve($row->item_id, $row->item_name);

                        return $row;
                    })
                    ->toArray();
            }
        );
    }
}
