<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketHistory;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\Support\MarketPeriod;
use App\Services\MarketCacheService;

/**
 * Most traded goods of a server within a period.
 */
final readonly class PopularItemService
{
    public function __construct(
        private ResourceNameResolver $names,
        private MarketCacheService $cache,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function popular(string $serverId, MarketPeriod $period, ?MarketItemKind $kind = null): array
    {
        $params = ['period' => $period->key];
        if ($kind !== null) {
            $params['kind'] = $kind->value;
        }

        return $this->cache->remember(
            $serverId,
            'popular',
            $params,
            (int) config('market.cache_ttl.popular'),
            function () use ($serverId, $period, $kind): array {
                $query = MarketHistory::query()
                    ->where('server_id', $serverId)
                    ->whereNotNull('item_id')
                    ->where('item_id', '!=', '')
                    ->selectRaw('item_id, item_name, item_kind, count(*) as offers_count, count(distinct player_id) as sellers_count, sum(volume) as total_volume');

                if ($kind !== null) {
                    $query->where('item_kind', $kind->value);
                }

                if ($period->isBounded()) {
                    $query->where('collected_at', '>=', $period->since);
                }

                /** @var list<array<string, mixed>> */
                return $query->groupBy('item_id', 'item_name', 'item_kind')
                    ->orderByDesc('offers_count')
                    ->orderByDesc('total_volume')
                    ->limit((int) config('market.popular_items_limit'))
                    ->get()
                    ->map(function (MarketHistory $row): array {
                        $kind = $row->item_kind->value;

                        return [
                            'item_id' => (string) $row->item_id,
                            'item_name' => $this->names->resolve((string) $row->item_id, (string) $row->item_name),
                            'kind' => $kind,
                            'offers_count' => (int) $row->offers_count,
                            'sellers_count' => (int) $row->sellers_count,
                            'total_volume' => (int) $row->total_volume,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );
    }
}
