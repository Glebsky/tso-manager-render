<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Enums\UpgradeRejectionReason;

final readonly class UpgradeDecision
{
    private function __construct(
        public bool $allowed,
        public UpgradeRejectionReason $reason,
        public ?MineDefinition $definition,
        public int $grid,
        public int $currentLevel = 0,
        public int $targetLevel = 0,
        public string $buildingName = '',
        public string $mineName = '',
        public int $freeSlots = 0,
    ) {}

    public static function allow(MineDefinition $d, int $grid, int $current, int $target, int $freeSlots = 0): self
    {
        return new self(
            allowed: true,
            reason: UpgradeRejectionReason::Ok,
            definition: $d,
            grid: $grid,
            currentLevel: $current,
            targetLevel: $target,
            buildingName: $d->buildingName,
            mineName: $d->buildingName,
            freeSlots: $freeSlots,
        );
    }

    public static function reject(
        UpgradeRejectionReason $reason,
        int $grid,
        int $current = 0,
        ?MineDefinition $d = null,
        int $target = 0,
        string $buildingName = '',
        int $freeSlots = 0,
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            definition: $d,
            grid: $grid,
            currentLevel: $current,
            targetLevel: $target,
            buildingName: $d !== null ? $d->buildingName : $buildingName,
            mineName: $d !== null ? $d->buildingName : '',
            freeSlots: $freeSlots,
        );
    }
}
