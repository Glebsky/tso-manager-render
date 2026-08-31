<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ProductionRecipe
{
    /**
     * @param  list<array{resource: string, count: int, is_population?: bool}>  $costs
     * @param  list<array{threshold: int, costs: list<array{resource: string, count: int, is_population?: bool}>}>|null  $costTiers
     */
    public function __construct(
        public string $name,
        public int|string $group,
        public int $durationSeconds,
        public string $buffType = 'Timed',
        public int $requiresUpgradeLevelMin = 0,
        public int $requiresUpgradeLevelMax = 99,
        public ?string $requiresEvent = null,
        public ?string $requiresQuest = null,
        public array $costs = [],
        public bool $costsKnown = true,
        public ?string $label = null,
        public ?int $instantFinishCost = null,
        public int $maxAmountPerOrder = 25,
        public int $maxStacksPerOrder = 200,
        public bool $stacksSupported = true,
        public bool $costIsLowerBound = false,
        public ?array $costTiers = null,
        public ?int $requiresPlayerLevelMin = null,
        public ?string $outputBuffName = null,
        public bool $unverifiedProtocol = false,
        public ?int $tier = null,
        public ?string $unitGroup = null,
    ) {}
}
