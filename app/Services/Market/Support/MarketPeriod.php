<?php

declare(strict_types=1);

namespace App\Services\Market\Support;

use Carbon\CarbonInterface;

/**
 * Immutable value object describing the analytics time window.
 *
 * Replaces the `$period` / `$dateFilter` / `$groupByExpression` triple of
 * loose variables that used to be recomputed in every controller method.
 */
final class MarketPeriod
{
    public function __construct(
        public readonly string $key,
        public readonly ?CarbonInterface $since,
        public readonly TimeGranularity $granularity,
    ) {}

    public function withGranularity(TimeGranularity $granularity): self
    {
        return new self($this->key, $this->since, $granularity);
    }

    public function isBounded(): bool
    {
        return $this->since !== null;
    }
}
