<?php

declare(strict_types=1);

namespace App\Services\Game\Mines\Contracts;

use App\Services\Game\Mines\MineDefinition;

interface MineCatalogInterface
{
    /** Определение по имени руды залежи, либо null. */
    public function findByDeposit(string $depositName): ?MineDefinition;

    /** Определение по имени здания шахты, либо null. */
    public function findByBuilding(string $buildingName): ?MineDefinition;

    /** @return list<MineDefinition> */
    public function all(): array;
}
