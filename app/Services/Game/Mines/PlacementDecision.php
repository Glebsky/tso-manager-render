<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Enums\PlacementRejectionReason;

final readonly class PlacementDecision
{
    private function __construct(
        public bool $allowed,
        public PlacementRejectionReason $reason,
        public ?MineDefinition $definition,
        public int $grid,
        public string $depositName = '',
        public string $mineName = '',
        public int $freeSlots = 0,
    ) {}

    public static function allow(MineDefinition $definition, int $grid, int $freeSlots = 0): self
    {
        return new self(
            allowed: true,
            reason: PlacementRejectionReason::Ok,
            definition: $definition,
            grid: $grid,
            depositName: $definition->depositName,
            mineName: $definition->buildingName,
            freeSlots: $freeSlots,
        );
    }

    public static function reject(
        PlacementRejectionReason $reason,
        int $grid,
        ?MineDefinition $definition = null,
        string $depositName = '',
        int $freeSlots = 0,
    ): self {
        return new self(
            allowed: false,
            reason: $reason,
            definition: $definition,
            grid: $grid,
            depositName: $definition !== null ? $definition->depositName : $depositName,
            mineName: $definition !== null ? $definition->buildingName : '',
            freeSlots: $freeSlots,
        );
    }
}
