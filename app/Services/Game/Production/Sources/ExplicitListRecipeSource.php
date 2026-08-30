<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

final readonly class ExplicitListRecipeSource implements RecipeSourceInterface
{
    /**
     * @param  array<int, array{type: string, recipes: list<array<string, mixed>>}>  $timedLists
     */
    public function __construct(
        private array $timedLists
    ) {}

    public function id(): string
    {
        return 'explicit_list';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        return $this->timedLists[$productionType]['recipes'] ?? [];
    }
}
