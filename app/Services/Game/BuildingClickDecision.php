<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\BuildingClickMode;
use App\Enums\CollectibleKind;

final readonly class BuildingClickDecision
{
    public function __construct(
        public BuildingClickMode $mode,
        public int $grid,
        public string $buildingName,
        public ?CollectibleKind $kind,
        public string $reason,
    ) {}
}
