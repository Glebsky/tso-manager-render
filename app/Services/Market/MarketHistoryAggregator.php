<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Models\MarketHistory;
use App\Services\Market\Support\MarketPeriod;
use App\Services\Market\Support\TimeBucket\TimeBucketExpression;
use App\Services\Market\Support\TimeBucket\TimeBucketExpressionFactory;
use App\Services\Market\Support\TimeGranularity;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * All aggregate reads over `market_history`.
 *
 * Consolidates logic that was duplicated between `getAnalytics()` and
 * `getBulk()`: price statistics, period totals, the time-bucketed series and
 * the "latest price per pair" lookup. Driver specific SQL is delegated to a
 * {@see TimeBucketExpression} strategy.
 */
final class MarketHistoryAggregator
{
    private const SERIES_AGGREGATES = 'avg(price) as price, sum(volume) as volume, count(distinct player_id) as sellers_count, count(*) as offers_count, round(avg(amount)) as avg_amount, round(avg(target_amount)) as avg_target_amount';

    private const PAIR_AGGREGATES = 'avg(price) as average_price, min(price) as min_price, max(price) as max_price, sum(volume) as total_volume, count(*) as offers_count, count(distinct player_id) as sellers_count';

    public function __construct(
        private readonly TimeBucketExpressionFactory $buckets,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * Oldest/newest data point of a pair, used to pick the chart granularity.
     *
     * @return array{min: ?CarbonInterface, max: ?CarbonInterface}
     */
    public function span(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): array
    {
        $min = $this->pairQuery($serverId, $itemId, $targetItemId, $period)->min('collected_at');
        $max = $this->pairQuery($serverId, $itemId, $targetItemId, $period)->max('collected_at');

        return [
            'min' => $min !== null ? Carbon::parse($min) : null,
            'max' => $max !== null ? Carbon::parse($max) : null,
        ];
    }

    /**
     * @return array{average: float, minimum: float, maximum: float, current: float}
     */
    public function priceStats(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): array
    {
        $stats = $this->pairQuery($serverId, $itemId, $targetItemId, $period)
            ->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
            ->first();

        return [
            'average' => round((float) ($stats->average_price ?? 0), 2),
            'minimum' => round((float) ($stats->min_price ?? 0), 2),
            'maximum' => round((float) ($stats->max_price ?? 0), 2),
            'current' => round((float) ($this->currentPrice($serverId, $itemId, $targetItemId) ?? 0), 2),
        ];
    }

    public function hasPrices(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): bool
    {
        return $this->pairQuery($serverId, $itemId, $targetItemId, $period)->whereNotNull('price')->exists();
    }

    public function currentPrice(string $serverId, string $itemId, string $targetItemId): ?float
    {
        $price = MarketHistory::query()
            ->where('server_id', $serverId)
            ->where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->orderByDesc('collected_at')
            ->value('price');

        return $price !== null ? (float) $price : null;
    }

    /**
     * @return array{volume: int, offers_count: int, sellers_count: int}
     */
    public function periodInfo(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): array
    {
        $summary = $this->pairQuery($serverId, $itemId, $targetItemId, $period)
            ->selectRaw('sum(volume) as total_volume, count(*) as offers_count, count(distinct player_id) as sellers_count')
            ->first();

        return [
            'volume' => (int) ($summary->total_volume ?? 0),
            'offers_count' => (int) ($summary->offers_count ?? 0),
            'sellers_count' => (int) ($summary->sellers_count ?? 0),
        ];
    }

    /**
     * Time bucketed price/volume series for one pair.
     *
     * @return list<array<string, mixed>>
     */
    public function series(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): array
    {
        $bucket = $this->bucketExpression($period->granularity);

        return $this->pairQuery($serverId, $itemId, $targetItemId, $period)
            ->selectRaw("{$bucket} as time_bucket, ".self::SERIES_AGGREGATES)
            ->groupBy('time_bucket')
            ->orderBy('time_bucket')
            ->get()
            ->map(fn ($row): array => $this->formatSeriesRow($row, $period->granularity))
            ->all();
    }

    /**
     * Aggregates for every pair of a server in one query (bulk endpoint).
     *
     * @return Collection<int, object>
     */
    public function statsByPair(string $serverId, CarbonInterface $since): Collection
    {
        return MarketHistory::query()
            ->where('server_id', $serverId)
            ->where('collected_at', '>=', $since)
            ->selectRaw('item_id, target_item_id, '.self::PAIR_AGGREGATES)
            ->groupBy('item_id', 'target_item_id')
            ->get()
            ->toBase();
    }

    /**
     * Series for every pair of a server keyed by "item_id|target_item_id".
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function seriesByPair(string $serverId, CarbonInterface $since, TimeGranularity $granularity): array
    {
        $bucket = $this->bucketExpression($granularity);

        $rows = MarketHistory::query()
            ->where('server_id', $serverId)
            ->where('collected_at', '>=', $since)
            ->selectRaw("item_id, target_item_id, {$bucket} as time_bucket, ".self::SERIES_AGGREGATES)
            ->groupBy('item_id', 'target_item_id', 'time_bucket')
            ->orderBy('item_id')
            ->orderBy('target_item_id')
            ->orderBy('time_bucket')
            ->get();

        $seriesByPair = [];

        foreach ($rows as $row) {
            $seriesByPair[$this->pairKey((string) $row->item_id, (string) $row->target_item_id)][] = $this->formatSeriesRow($row, $granularity);
        }

        return $seriesByPair;
    }

    /**
     * Latest known price for every pair of a server, keyed by pair key.
     *
     * @return array<string, float>
     */
    public function latestPricesByPair(string $serverId): array
    {
        $connection = $this->db->connection();

        if ($connection->getDriverName() === 'pgsql') {
            $rows = $connection->select(
                'SELECT DISTINCT ON (item_id, target_item_id) item_id, target_item_id, price
                 FROM market_history
                 WHERE server_id = ?
                 ORDER BY item_id, target_item_id, collected_at DESC',
                [$serverId]
            );
        } else {
            $rows = $connection->select(
                'SELECT mh.item_id, mh.target_item_id, mh.price
                 FROM market_history mh
                 INNER JOIN (
                     SELECT item_id, target_item_id, MAX(collected_at) as max_collected
                     FROM market_history
                     WHERE server_id = ?
                     GROUP BY item_id, target_item_id
                 ) latest ON mh.item_id = latest.item_id
                     AND mh.target_item_id = latest.target_item_id
                     AND mh.collected_at = latest.max_collected
                 WHERE mh.server_id = ?',
                [$serverId, $serverId]
            );
        }

        $prices = [];

        foreach ($rows as $row) {
            $prices[$this->pairKey((string) $row->item_id, (string) $row->target_item_id)] = round((float) ($row->price ?? 0), 2);
        }

        return $prices;
    }

    public function pairKey(string $itemId, string $targetItemId): string
    {
        return $itemId.'|'.$targetItemId;
    }

    private function pairQuery(string $serverId, string $itemId, string $targetItemId, MarketPeriod $period): Builder
    {
        return MarketHistory::query()
            ->where('server_id', $serverId)
            ->where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->when($period->isBounded(), fn (Builder $query) => $query->where('collected_at', '>=', $period->since));
    }

    private function bucketExpression(TimeGranularity $granularity): string
    {
        return $this->buckets
            ->forDriver($this->db->connection()->getDriverName())
            ->expression($granularity);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSeriesRow(object $row, TimeGranularity $granularity): array
    {
        $bucket = $row->time_bucket;
        $date = $bucket instanceof \DateTimeInterface ? Carbon::instance($bucket) : Carbon::parse((string) $bucket);

        return [
            'collected_at' => $date->format($granularity->displayFormat()),
            'price' => round((float) $row->price, 4),
            'volume' => (int) $row->volume,
            'sellers_count' => (int) $row->sellers_count,
            'offers_count' => (int) $row->offers_count,
            'avg_amount' => (int) round((float) ($row->avg_amount ?? 1)),
            'avg_target_amount' => (int) round((float) ($row->avg_target_amount ?? 1)),
        ];
    }
}
