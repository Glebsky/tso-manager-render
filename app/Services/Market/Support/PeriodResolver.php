<?php

declare(strict_types=1);

namespace App\Services\Market\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Translates the client `period` parameter into a {@see MarketPeriod}.
 *
 * The same `match ($period) { '1d' => ..., '7d' => ... }` block was duplicated
 * in getAnalytics(), getBulk() and getPopularItems(). It now lives here only.
 */
final class PeriodResolver
{
    public const string DEFAULT_PERIOD = 'all';

    /** @var list<string> */
    public const array SUPPORTED_PERIODS = ['all', '1d', '7d', '30d', '1y'];

    public function resolve(?string $period, ?CarbonInterface $now = null): MarketPeriod
    {
        $key = in_array($period, self::SUPPORTED_PERIODS, true) ? $period : self::DEFAULT_PERIOD;
        $reference = $now !== null ? $now->copy() : Carbon::now();

        $since = match ($key) {
            '1d' => $reference->copy()->subDay(),
            '7d' => $reference->copy()->subDays(7),
            '30d' => $reference->copy()->subDays(30),
            '1y' => $reference->copy()->subYear(),
            default => null,
        };

        return new MarketPeriod($key, $since, $this->defaultGranularity($key));
    }

    /**
     * Refine the granularity using the real data span, mirroring the previous
     * inline heuristic (<=2 days => hour, <=90 => day, <=730 => week, else month).
     */
    public function refineGranularity(MarketPeriod $period, ?CarbonInterface $min, ?CarbonInterface $max): MarketPeriod
    {
        if ($min === null || $max === null) {
            return $period->withGranularity($this->defaultGranularity($period->key));
        }

        $daysSpan = $min->copy()->startOfDay()->diffInDays($max->copy()->startOfDay());

        $granularity = match (true) {
            $period->key === '1d' || $daysSpan <= 2 => TimeGranularity::Hour,
            $daysSpan <= 90 => TimeGranularity::Day,
            $daysSpan <= 730 => TimeGranularity::Week,
            default => TimeGranularity::Month,
        };

        return $period->withGranularity($granularity);
    }

    private function defaultGranularity(string $periodKey): TimeGranularity
    {
        return $periodKey === '1d' ? TimeGranularity::Hour : TimeGranularity::Day;
    }
}
