<?php

declare(strict_types=1);

namespace App\Services\Market\Support\TimeBucket;

use App\Services\Market\Support\TimeGranularity;

/**
 * Strategy that produces the database-specific SQL expression used to bucket
 * `collected_at` values for aggregation.
 *
 * Adding a new database engine now means adding one implementation instead of
 * editing three copies of an `if ($driver === 'pgsql') ... elseif ...` chain
 * (Open/Closed Principle).
 */
interface TimeBucketExpression
{
    public function supports(string $driver): bool;

    public function expression(TimeGranularity $granularity, string $column = 'collected_at'): string;
}
