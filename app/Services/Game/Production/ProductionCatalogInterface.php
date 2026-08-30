<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

interface ProductionCatalogInterface
{
    /**
     * @return int|null productionType or null if building is not a producer
     */
    public function productionTypeFor(string $buildingName): ?int;

    /**
     * @return list<ProductionRecipe>
     */
    public function recipesFor(int $productionType): array;

    public function findRecipe(int $productionType, string $recipeName): ?ProductionRecipe;

    /**
     * @return array<string, int> buildingName => productionType
     */
    public function allProducers(): array;
}
