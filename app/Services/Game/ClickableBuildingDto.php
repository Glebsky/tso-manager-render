<?php

declare(strict_types=1);

namespace App\Services\Game;

final readonly class ClickableBuildingDto
{
    public function __construct(
        public int $grid,
        public string $buildingName,
        public string $kind,        // collectible | quest_gift | none
        public ?bool $available,    // null = unknown
    ) {}

    /**
     * @return array{grid: int, building_name: string, kind: string, available: bool|null}
     */
    public function toArray(): array
    {
        return [
            'grid' => $this->grid,
            'building_name' => $this->buildingName,
            'kind' => $this->kind,
            'available' => $this->available,
        ];
    }
}
