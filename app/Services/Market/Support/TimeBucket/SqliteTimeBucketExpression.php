<?php

declare(strict_types=1);

namespace App\Services\Market\Support\TimeBucket;

use App\Services\Market\Support\TimeGranularity;

final class SqliteTimeBucketExpression implements TimeBucketExpression
{
    public function supports(string $driver): bool
    {
        return $driver === 'sqlite';
    }

    public function expression(TimeGranularity $granularity, string $column = 'collected_at'): string
    {
        return match ($granularity) {
            TimeGranularity::Hour => sprintf("strftime('%%Y-%%m-%%d %%H:00:00', %s)", $column),
            TimeGranularity::Week => sprintf("date(%s, 'weekday 0', '-6 days')", $column),
            TimeGranularity::Month => sprintf("strftime('%%Y-%%m-01', %s)", $column),
            TimeGranularity::Day => sprintf("strftime('%%Y-%%m-%%d', %s)", $column),
        };
    }
}
