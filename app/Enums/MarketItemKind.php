<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketItemKind: string
{
    case Resource = 'resource';
    case Buff = 'buff';
    case Adventure = 'adventure';
    case Building = 'building';

    /** Код секции локализации по умолчанию (data-sources.md §4.1). */
    public function locaSection(): string
    {
        return match ($this) {
            self::Adventure => 'ADN',
            self::Building => 'BUI',
            self::Resource, self::Buff => 'RES',
        };
    }

    /** Префикс композитного id; для ресурса префикса нет. */
    public function prefix(): ?string
    {
        return $this === self::Resource ? null : $this->value;
    }
}
