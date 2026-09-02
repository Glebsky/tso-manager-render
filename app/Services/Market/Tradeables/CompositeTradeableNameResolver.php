<?php

declare(strict_types=1);

namespace App\Services\Market\Tradeables;

use App\Enums\MarketItemKind;
use App\Services\Lang\GameTranslationResolver;
use App\Services\Market\Contracts\ResourceNameResolver;

final readonly class CompositeTradeableNameResolver implements ResourceNameResolver
{
    public function __construct(
        private GameTranslationResolver $translations,
        private TradeableIdFactory $ids,
    ) {}

    public function resolve(?string $itemId, ?string $legacyName = null): string
    {
        if ($itemId === null || $itemId === '') {
            return $legacyName ?? '';
        }

        $fallback = ($legacyName !== null && $legacyName !== '') ? $legacyName : null;
        ['kind' => $kind, 'base' => $base, 'subject' => $subject] = $this->ids->parse($itemId);

        return $this->resolveFromParsed($kind, $base, $subject, null, $fallback);
    }

    public function resolveTradeSide(TradeSide $side, ?string $legacyName = null): string
    {
        $fallback = ($legacyName !== null && $legacyName !== '') ? $legacyName : null;

        return $this->resolveFromParsed(
            $side->kind,
            $side->baseName,
            $side->subject,
            $side->rawAmount,
            $fallback,
        );
    }

    private function resolveFromParsed(
        MarketItemKind $kind,
        string $base,
        ?string $subject,
        ?int $rawAmount,
        ?string $fallback,
    ): string {
        $defaultFallback = $fallback ?? ($subject ?? $base);

        return match ($kind) {
            MarketItemKind::Resource => $this->translations->name('RES', $base, $defaultFallback),
            MarketItemKind::Adventure => $this->translations->name('ADN', $subject ?? $base, $defaultFallback),
            MarketItemKind::Building => $this->translations->name('BUI', $subject ?? $base, $defaultFallback),
            MarketItemKind::Buff => $this->resolveBuffName($base, $subject, $rawAmount, $defaultFallback),
        };
    }

    private function resolveBuffName(
        string $base,
        ?string $subject,
        ?int $rawAmount,
        string $fallback,
    ): string {
        if (
            str_starts_with($base, 'ProductivityBuff')
            || str_starts_with($base, 'SpeedUpPopulationGrowth')
            || str_starts_with($base, 'RecruitingBuff')
        ) {
            return $this->translations->name('RES', $base, $fallback);
        }

        if (
            str_starts_with($base, 'AddResource')
            || str_starts_with($base, 'FillDeposit')
            || $base === 'HiredMilitary'
        ) {
            $key = ($base === 'FillDeposit' && $subject === null)
                ? 'FillDepositAny'
                : $base;

            $amountParam = $rawAmount !== null && $rawAmount > 0 ? $rawAmount : '';

            return $this->translations->resolve(
                'RES',
                $key,
                [$amountParam, $subject ?? ''],
                $fallback,
            );
        }

        if (str_starts_with($base, 'ChangeColorScheme') && $subject !== null) {
            return $this->translations->name('RES', $base.'_'.$subject, $fallback);
        }

        return $this->translations->name('RES', $base, $fallback);
    }
}
