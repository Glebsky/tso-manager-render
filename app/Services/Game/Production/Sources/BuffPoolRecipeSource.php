<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

final readonly class BuffPoolRecipeSource implements RecipeSourceInterface
{
    /**
     * @param  array<string, array<string, mixed>>  $buffPool
     */
    public function __construct(
        private array $buffPool
    ) {}

    public function id(): string
    {
        return 'buff_pool';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        return array_values($this->buffPool);
    }
}
