<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

final readonly class UnsupportedRecipeSource implements RecipeSourceInterface
{
    public function __construct(
        private string $reason = 'not_supported'
    ) {}

    public function id(): string
    {
        return "unsupported:{$this->reason}";
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        return [];
    }
}
