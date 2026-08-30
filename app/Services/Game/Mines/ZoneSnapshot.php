<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class ZoneSnapshot
{
    /**
     * @param  array<int, DepositSnapshot>  $depositsByGrid
     * @param  array<int, BuildingSnapshot>  $buildingsByGrid
     */
    public function __construct(
        private array $depositsByGrid,
        private array $buildingsByGrid,
        private ?BuildQueueSnapshot $buildQueue,
    ) {}

    public function depositAt(int $grid): ?DepositSnapshot
    {
        return $this->depositsByGrid[$grid] ?? null;
    }

    public function buildingAt(int $grid): ?BuildingSnapshot
    {
        return $this->buildingsByGrid[$grid] ?? null;
    }

    public function hasBuildingAt(int $grid): bool
    {
        return isset($this->buildingsByGrid[$grid]);
    }

    public function buildQueueBudget(): BuildQueueBudget
    {
        return new BuildQueueBudget($this->buildQueue);
    }

    /**
     * @return list<DepositSnapshot>
     */
    public function deposits(): array
    {
        return array_values($this->depositsByGrid);
    }

    /**
     * @return list<BuildingSnapshot>
     */
    public function buildings(): array
    {
        return array_values($this->buildingsByGrid);
    }
}
