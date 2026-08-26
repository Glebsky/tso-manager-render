<?php

declare(strict_types=1);

namespace App\Services\Market\Support;

/**
 * Whitelisted time-bucket granularities.
 *
 * The previous implementation passed a raw `$groupByExpression` string
 * straight into `selectRaw()`. Modeling it as an enum makes the set of legal
 * values explicit and removes any possibility of identifier injection.
 */
enum TimeGranularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public static function fromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Day;
    }

    /**
     * Display format used by the frontend charts. Kept identical to the
     * previously hard-coded formats to preserve the API contract.
     */
    public function displayFormat(): string
    {
        return match ($this) {
            self::Hour => 'd.m.Y H:i',
            self::Month => 'm.Y',
            self::Day, self::Week => 'd.m.Y',
        };
    }
}
