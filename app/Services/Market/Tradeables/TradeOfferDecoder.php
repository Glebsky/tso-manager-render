<?php

declare(strict_types=1);

namespace App\Services\Market\Tradeables;

use App\Enums\MarketItemKind;

final readonly class TradeOfferDecoder
{
    public const int TRADE_RES_FOR_RES = 0;

    public const int TRADE_RES_FOR_BUFF = 1;

    public const int TRADE_BUFF_FOR_RES = 2;

    public const int TRADE_BUFF_FOR_BUFF = 3;

    public const string ADVENTURE_BUFF = 'Adventure';

    public const string BUILD_BUILDING_BUFF = 'BuildBuilding';

    public const string BUILD_DEFENSE_MODE_BUILDING_BUFF = 'BuildDefenseModeBuilding';

    /**
     * @return DecodedTradeOffer|null null — лот невалиден и должен быть пропущен
     */
    public function decode(string $offer, int $tradeType): ?DecodedTradeOffer
    {
        if ($tradeType < self::TRADE_RES_FOR_RES || $tradeType > self::TRADE_BUFF_FOR_BUFF) {
            return null;
        }

        $parts = explode('|', $offer);
        if (count($parts) !== 3) {
            return null;
        }

        $offerPart = trim($parts[0]);
        $costPart = trim($parts[1]);
        $totalLotsStr = trim($parts[2]);

        $offerSide = match ($tradeType) {
            self::TRADE_RES_FOR_RES, self::TRADE_RES_FOR_BUFF => $this->parseResourceSide($offerPart),
            self::TRADE_BUFF_FOR_RES, self::TRADE_BUFF_FOR_BUFF => $this->parseBuffSide($offerPart),
        };

        if ($offerSide === null) {
            return null;
        }

        if ($costPart === '@') {
            $costSide = null;
        } else {
            $costSide = match ($tradeType) {
                self::TRADE_RES_FOR_RES, self::TRADE_BUFF_FOR_RES => $this->parseResourceSide($costPart),
                self::TRADE_RES_FOR_BUFF, self::TRADE_BUFF_FOR_BUFF => $this->parseBuffSide($costPart),
            };

            if ($costSide === null) {
                return null;
            }
        }

        $totalLots = $this->parseAmount($totalLotsStr) ?? 0;

        return new DecodedTradeOffer(
            offer: $offerSide,
            costs: $costSide,
            totalLots: $totalLots,
            tradeType: $tradeType,
        );
    }

    private function parseResourceSide(string $side): ?TradeSide
    {
        $f = explode(',', $side);
        if (count($f) < 2) {
            return null;
        }

        $baseName = trim($f[0]);
        if ($baseName === '') {
            return null;
        }

        $rawAmount = $this->parseAmount(trim($f[1]));
        if ($rawAmount === null || $rawAmount <= 0) {
            return null;
        }

        return new TradeSide(
            kind: MarketItemKind::Resource,
            baseName: $baseName,
            subject: null,
            rawAmount: $rawAmount,
            units: $rawAmount,
            recurringChance: null,
        );
    }

    private function parseBuffSide(string $side): ?TradeSide
    {
        $f = explode(',', $side);

        $baseName = trim($f[0]);
        if ($baseName === '') {
            return null;
        }

        $subjectRaw = isset($f[1]) ? trim($f[1]) : '';
        $subject = $subjectRaw === '' ? null : $subjectRaw;
        if ($subject !== null && strcasecmp($subject, $baseName) === 0) {
            $subject = null;
        }

        $rawAmount = isset($f[2]) ? $this->parseAmount(trim($f[2])) : null;
        $recurringChance = isset($f[3]) ? $this->parseAmount(trim($f[3])) : null;

        $kind = match ($baseName) {
            self::ADVENTURE_BUFF => MarketItemKind::Adventure,
            self::BUILD_BUILDING_BUFF, self::BUILD_DEFENSE_MODE_BUILDING_BUFF => MarketItemKind::Building,
            default => MarketItemKind::Buff,
        };

        if ($kind === MarketItemKind::Adventure && $subject === null) {
            return null;
        }

        return new TradeSide(
            kind: $kind,
            baseName: $baseName,
            subject: $subject,
            rawAmount: $rawAmount,
            units: 1,
            recurringChance: $recurringChance,
        );
    }

    private function parseAmount(string $v): ?int
    {
        return ctype_digit($v) ? (int) $v : null;
    }
}
