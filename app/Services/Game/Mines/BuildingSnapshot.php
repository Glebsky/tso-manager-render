<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class BuildingSnapshot
{
    public function __construct(
        public int $grid,
        public string $name,
        public int $upgradeLevel,
        public bool $isProductionActive,
        public bool $upgradeInProgress,
    ) {}
}
