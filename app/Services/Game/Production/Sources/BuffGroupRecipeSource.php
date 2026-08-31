<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use InvalidArgumentException;

final readonly class BuffGroupRecipeSource implements RecipeSourceInterface
{
    /**
     * @param  array<string, array<string, mixed>>  $buffPool
     */
    public function __construct(
        private array $buffPool
    ) {}

    public function id(): string
    {
        return 'buff_group';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        if (! isset($options['group']) || (! is_int($options['group']) && ! ctype_digit((string) $options['group']))) {
            throw new InvalidArgumentException("BuffGroupRecipeSource requires an integer 'group' option for productionType {$productionType}");
        }

        $targetGroup = (int) $options['group'];
        $filtered = [];

        foreach ($this->buffPool as $buff) {
            $buffGroup = (int) ($buff['group'] ?? -1);
            if ($buffGroup === $targetGroup) {
                $filtered[] = $buff;
            }
        }

        return $filtered;
    }
}
