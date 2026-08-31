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

    /**
     * @return string|null source identifier, e.g. 'explicit_list', 'buff_pool', 'unsupported:...'
     */
    public function recipeSourceFor(int $productionType): ?string;

    /**
     * @return array<string, mixed>
     */
    public function metadataFor(int $productionType): array;

    /**
     * @return bool false if productionType has source unsupported:*
     */
    public function isTypeSupported(int $productionType): bool;
}
