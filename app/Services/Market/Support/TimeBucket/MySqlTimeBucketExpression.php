<?php

declare(strict_types=1);

namespace App\Services\Market\Support\TimeBucket;

use App\Services\Market\Support\TimeGranularity;

final class MySqlTimeBucketExpression implements TimeBucketExpression
{
    public function supports(string $driver): bool
    {
        return $driver === 'mysql' || $driver === 'mariadb';
    }

    public function expression(TimeGranularity $granularity, string $column = 'collected_at'): string
    {
        return match ($granularity) {
            TimeGranularity::Hour => sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:00:00')", $column),
            TimeGranularity::Week => sprintf("DATE_FORMAT(DATE_SUB(%s, INTERVAL WEEKDAY(%s) DAY), '%%Y-%%m-%%d')", $column, $column),
            TimeGranularity::Month => sprintf("DATE_FORMAT(%s, '%%Y-%%m-01')", $column),
            TimeGranularity::Day => sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d')", $column),
        };
    }
}
