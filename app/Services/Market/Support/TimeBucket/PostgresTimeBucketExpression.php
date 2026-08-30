<?php

declare(strict_types=1);

namespace App\Services\Market\Support\TimeBucket;

use App\Services\Market\Support\TimeGranularity;

final class PostgresTimeBucketExpression implements TimeBucketExpression
{
    public function supports(string $driver): bool
    {
        return $driver === 'pgsql';
    }

    public function expression(TimeGranularity $granularity, string $column = 'collected_at'): string
    {
        return sprintf("date_trunc('%s', %s)", $granularity->value, $column);
    }
}
