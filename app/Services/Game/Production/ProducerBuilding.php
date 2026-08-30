<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ProducerBuilding
{
    public function __construct(
        public string $name,
        public int $productionType,
        public ?string $label = null,
    ) {}
}
