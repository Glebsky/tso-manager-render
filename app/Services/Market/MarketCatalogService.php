<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Enums\MarketItemKind;
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
final readonly class MarketCatalogService
{
    public function __construct(
        private ResourceNameResolver $names,
        private MarketCacheService $cache,
    ) {}

    /**
     * @return list<array{item_id: string, item_name: string, kind: string}>
     */
    public function goods(string $serverId, ?MarketItemKind $kind = null): array
    {
        $params = $kind !== null ? ['kind' => $kind->value] : [];

        return $this->cache->remember(
            $serverId,
            'goods',
            $params,
            (int) config('market.cache_ttl.goods'),
            fn (): array => $this->distinctGoods($serverId, $kind)
        );
    }

    /**
     * @return list<array{target_item_id: string, target_item_name: string, kind: string}>
     */
    public function targets(string $serverId, string $itemId, ?MarketItemKind $kind = null): array
    {
        $params = ['item_id' => $itemId];
        if ($kind !== null) {
            $params['kind'] = $kind->value;
        }

        return $this->cache->remember(
            $serverId,
            'targets',
            $params,
            (int) config('market.cache_ttl.targets'),
            fn (): array => $this->distinctTargets($serverId, $itemId, $kind)
        );
    }

    /**
     * @return list<array{item_id: string, item_name: string, kind: string}>
     */
    public function distinctGoods(string $serverId, ?MarketItemKind $kind = null): array
    {
        $offers = MarketOffer::query()->where('server_id', $serverId)->whereNotNull('item_id')->where('item_id', '!=', 'Adventure')->select('item_id', 'item_name', 'item_kind');
        $history = MarketHistory::query()->where('server_id', $serverId)->whereNotNull('item_id')->where('item_id', '!=', 'Adventure')->select('item_id', 'item_name', 'item_kind');

        if ($kind !== null) {
            $offers->where('item_kind', $kind->value);
            $history->where('item_kind', $kind->value);
        }

        /** @var list<array{item_id: string, item_name: string, kind: string}> */
        return $this->distinctPairColumn(
            $offers,
            $history,
            'item_id',
            'item_name',
            'item_kind',
        );
    }

    /**
     * @return list<array{target_item_id: string, target_item_name: string, kind: string}>
     */
    public function distinctTargets(string $serverId, string $itemId, ?MarketItemKind $kind = null): array
    {
        $offers = MarketOffer::query()->where('server_id', $serverId)->where('item_id', $itemId)->whereNotNull('target_item_id')->where('target_item_id', '!=', 'Adventure')->select('target_item_id', 'target_item_name', 'target_item_kind');
        $history = MarketHistory::query()->where('server_id', $serverId)->where('item_id', $itemId)->whereNotNull('target_item_id')->where('target_item_id', '!=', 'Adventure')->select('target_item_id', 'target_item_name', 'target_item_kind');

        if ($kind !== null) {
            $offers->where('target_item_kind', $kind->value);
            $history->where('target_item_kind', $kind->value);
        }

        /** @var list<array{target_item_id: string, target_item_name: string, kind: string}> */
        return $this->distinctPairColumn(
            $offers,
            $history,
            'target_item_id',
            'target_item_name',
            'target_item_kind',
        );
    }

    /**
     * @param  list<array{item_id: string, item_name: string, kind?: string}>  $goods
     * @return array<string, list<array{target_item_id: string, target_item_name: string, kind: string}>>
     */
    public function targetsMap(string $serverId, array $goods, ?MarketItemKind $kind = null): array
    {
        $map = [];

        foreach ($goods as $good) {
            $itemId = (string) $good['item_id'];
            $map[$itemId] = $this->distinctTargets($serverId, $itemId, $kind);
        }

        return $map;
    }

    /**
     * @param  Builder<MarketOffer>  $offers
     * @param  Builder<MarketHistory>  $history
     * @return list<array<string, string>>
     */
    private function distinctPairColumn(Builder $offers, Builder $history, string $idColumn, string $nameColumn, string $kindColumn): array
    {
        /** @var list<array<string, string>> */
        return $offers->union($history)
            ->distinct()
            ->orderBy($nameColumn)
            ->get()
            ->unique($idColumn)
            ->filter(fn (MarketOffer $row): bool => (string) $row->{$idColumn} !== '' && strcasecmp((string) $row->{$idColumn}, 'Adventure') !== 0)
            ->map(function (MarketOffer $row) use ($idColumn, $nameColumn, $kindColumn): array {
                $rawKind = $row->{$kindColumn};
                $kind = $rawKind instanceof MarketItemKind ? $rawKind->value : (string) ($rawKind ?? 'resource');

                return [
                    $idColumn => (string) $row->{$idColumn},
                    $nameColumn => $this->names->resolve((string) $row->{$idColumn}, (string) $row->{$nameColumn}),
                    'kind' => $kind,
                ];
            })
            ->sortBy($nameColumn, SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
