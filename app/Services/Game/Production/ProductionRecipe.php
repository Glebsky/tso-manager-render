<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ProductionRecipe
{
    /**
     * @param  list<array{resource: string, count: int}>  $costs
     */
    public function __construct(
        public string $name,
        public int|string $group,
        public int $durationSeconds,
        public string $buffType,
        public int $requiresUpgradeLevelMin,
        public int $requiresUpgradeLevelMax,
        public ?string $requiresEvent,
        public ?string $requiresQuest,
        public array $costs,
        public bool $costsKnown,
        public ?string $label = null,
    ) {}
}
