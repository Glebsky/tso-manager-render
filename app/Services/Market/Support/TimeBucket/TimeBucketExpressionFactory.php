<?php

declare(strict_types=1);

namespace App\Services\Market\Support\TimeBucket;

/**
 * Selects the {@see TimeBucketExpression} strategy matching the active
 * database driver. Registered strategies are injected, so no consumer needs
 * to know which engines exist.
 */
final class TimeBucketExpressionFactory
{
    /**
     * @param  list<TimeBucketExpression>  $expressions
     */
    public function __construct(
        private readonly array $expressions,
        private readonly TimeBucketExpression $fallback,
    ) {}

    public function forDriver(string $driver): TimeBucketExpression
    {
        foreach ($this->expressions as $expression) {
            if ($expression->supports($driver)) {
                return $expression;
            }
        }

        return $this->fallback;
    }
}
