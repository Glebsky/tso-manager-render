<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

interface RecipeSourceInterface
{
    /**
     * Stable identifier stored in metadata[type].recipe_source.
     * Examples: 'explicit_list', 'buff_pool', 'military_units', 'skillpoints', 'collections', 'combat3_units'.
     */
    public function id(): string;

    /**
     * Return array of recipes for the given productionType.
     *
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array;
}
