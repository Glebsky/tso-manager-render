<?php

declare(strict_types=1);

namespace App\Services\Market\Tradeables;

use App\Enums\MarketItemKind;

final readonly class TradeableIdFactory
{
    public const string SEPARATOR = ':';

    public function fromSide(TradeSide $side): string
    {
        return match ($side->kind) {
            MarketItemKind::Resource => $side->baseName,
            MarketItemKind::Adventure => 'adventure:'.($side->subject ?? $side->baseName),
            MarketItemKind::Building => 'building:'.($side->subject ?? $side->baseName),
            MarketItemKind::Buff => ($side->subject !== null && $side->subject !== '' && $side->subject !== $side->baseName)
                ? 'buff:'.$side->baseName.':'.$side->subject
                : 'buff:'.$side->baseName,
        };
    }

    /**
     * @return array{kind: MarketItemKind, base: string, subject: ?string}
     */
    public function parse(string $itemId): array
    {
        $parts = explode(self::SEPARATOR, $itemId);
        $prefix = $parts[0];

        $kind = MarketItemKind::tryFrom($prefix);

        if ($kind === null || $kind === MarketItemKind::Resource) {
            return [
                'kind' => MarketItemKind::Resource,
                'base' => $itemId,
                'subject' => null,
            ];
        }

        if ($kind === MarketItemKind::Buff) {
            $base = $parts[1] ?? '';
            $subjectRaw = isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null;
            $subject = ($subjectRaw !== null && strcasecmp($subjectRaw, $base) !== 0) ? $subjectRaw : null;

            return [
                'kind' => MarketItemKind::Buff,
                'base' => $base,
                'subject' => $subject,
            ];
        }

        $subject = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;
        $base = $kind === MarketItemKind::Adventure
            ? TradeOfferDecoder::ADVENTURE_BUFF
            : TradeOfferDecoder::BUILD_BUILDING_BUFF;

        return [
            'kind' => $kind,
            'base' => $base,
            'subject' => $subject,
        ];
    }
}
