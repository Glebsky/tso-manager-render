<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\MarketCacheService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Traded goods and trade targets available on a server.
 *
 * The "union offers + history, unique, resolve name, natural sort" pipeline
 * was copy-pasted four times in the old controller (goods, targets, bulk
 * goods, bulk targets map). It exists once here.
 */
final class MarketCatalogService
{
    public function __construct(
        private readonly ResourceNameResolver $names,
        private readonly MarketCacheService $cache,
    ) {}

    /**
     * @return list<array{item_id: string, item_name: string}>
     */
    public function goods(string $serverId): array
    {
        return $this->cache->remember(
            $serverId,
            'goods',
            [],
            (int) config('market.cache_ttl.goods'),
            fn (): array => $this->distinctGoods($serverId)
        );
    }

    /**
     * @return list<array{target_item_id: string, target_item_name: string}>
     */
    public function targets(string $serverId, string $itemId): array
    {
        return $this->cache->remember(
            $serverId,
            'targets',
            ['item_id' => $itemId],
            (int) config('market.cache_ttl.targets'),
            fn (): array => $this->distinctTargets($serverId, $itemId)
        );
    }

    /**
     * @return list<array{item_id: string, item_name: string}>
     */
    public function distinctGoods(string $serverId): array
    {
        return $this->distinctPairColumn(
            MarketOffer::query()->where('server_id', $serverId)->select('item_id', 'item_name'),
            MarketHistory::query()->where('server_id', $serverId)->select('item_id', 'item_name'),
            'item_id',
            'item_name'
        );
    }

    /**
     * @return list<array{target_item_id: string, target_item_name: string}>
     */
    public function distinctTargets(string $serverId, string $itemId): array
    {
        return $this->distinctPairColumn(
            MarketOffer::query()->where('server_id', $serverId)->where('item_id', $itemId)->select('target_item_id', 'target_item_name'),
            MarketHistory::query()->where('server_id', $serverId)->where('item_id', $itemId)->select('target_item_id', 'target_item_name'),
            'target_item_id',
            'target_item_name'
        );
    }

    /**
     * @param  list<array{item_id: string, item_name: string}>  $goods
     * @return array<string, list<array{target_item_id: string, target_item_name: string}>>
     */
    public function targetsMap(string $serverId, array $goods): array
    {
        $map = [];

        foreach ($goods as $good) {
            $itemId = (string) $good['item_id'];
            $map[$itemId] = $this->distinctTargets($serverId, $itemId);
        }

        return $map;
    }

    /**
     * @return list<array<string, string>>
     */
    private function distinctPairColumn(Builder $offers, Builder $history, string $idColumn, string $nameColumn): array
    {
        return $offers->union($history)
            ->distinct()
            ->orderBy($nameColumn)
            ->get()
            ->unique($idColumn)
            ->map(fn ($row): array => [
                $idColumn => $row->{$idColumn},
                $nameColumn => $this->names->resolve($row->{$idColumn}, $row->{$nameColumn}),
            ])
            ->sortBy($nameColumn, SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->toArray();
    }
}
