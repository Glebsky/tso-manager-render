<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ProductionCost
{
    /**
     * @param  array<string, int>  $resources  resource_name => quantity (excluding Population)
     */
    public function __construct(
        public array $resources,
        public int $durationSeconds,
        public bool $complete,
        public int $population = 0,
        public bool $isLowerBound = false,
    ) {}

    public function plus(self $other): self
    {
        $combinedResources = $this->resources;
        foreach ($other->resources as $res => $count) {
            $combinedResources[$res] = ($combinedResources[$res] ?? 0) + $count;
        }
        ksort($combinedResources, SORT_STRING);

        return new self(
            resources: $combinedResources,
            durationSeconds: $this->durationSeconds + $other->durationSeconds,
            complete: $this->complete && $other->complete,
            population: $this->population + $other->population,
            isLowerBound: $this->isLowerBound || $other->isLowerBound,
        );
    }
}
