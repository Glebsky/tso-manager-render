<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class MineDefinition
{
    public function __construct(
        public string $depositName,
        public string $buildingName,
        public int $buildingNumber,
        public int $maxUpgradeLevel,
    ) {}
}
