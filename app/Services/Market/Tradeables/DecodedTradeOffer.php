<?php

declare(strict_types=1);

namespace App\Services\Market\Tradeables;

final readonly class DecodedTradeOffer
{
    public function __construct(
        public TradeSide $offer,
        public ?TradeSide $costs,
        public int $totalLots,
        public int $tradeType,
    ) {}
}
