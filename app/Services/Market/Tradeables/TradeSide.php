<?php

declare(strict_types=1);

namespace App\Services\Market\Tradeables;

use App\Enums\MarketItemKind;

final readonly class TradeSide
{
    public function __construct(
        public MarketItemKind $kind,
        public string $baseName,
        public ?string $subject,
        public ?int $rawAmount,
        public int $units,
        public ?int $recurringChance = null,
    ) {}
}
