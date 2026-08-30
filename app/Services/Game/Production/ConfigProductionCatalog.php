<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ConfigProductionCatalog implements ProductionCatalogInterface
{
    /**
     * @param  array{
     *     producers?: array<string, int>,
     *     recipes?: array<int|string, list<array<string, mixed>>>,
     *     metadata?: array<int|string, array<string, mixed>>
     * }  $config
     */
    public function __construct(
        private array $config = []
    ) {}

    public function productionTypeFor(string $buildingName): ?int
    {
        $producers = $this->config['producers'] ?? [];

        return isset($producers[$buildingName]) ? (int) $producers[$buildingName] : null;
    }

    /**
     * @return list<ProductionRecipe>
     */
    public function recipesFor(int $productionType): array
    {
        $recipesData = $this->config['recipes'][$productionType] ?? [];
        if ($recipesData === []) {
            return [];
        }

        $recipes = [];
        foreach ($recipesData as $data) {
            $recipes[] = $this->hydrateRecipe($data);
        }

        return $recipes;
    }

    public function findRecipe(int $productionType, string $recipeName): ?ProductionRecipe
    {
        $recipes = $this->recipesFor($productionType);
        foreach ($recipes as $recipe) {
            if ($recipe->name === $recipeName) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    public function allProducers(): array
    {
        return (array) ($this->config['producers'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrateRecipe(array $data): ProductionRecipe
    {
        /** @var list<array{resource: string, count: int}> $costs */
        $costs = [];
        if (isset($data['costs']) && is_array($data['costs'])) {
            foreach ($data['costs'] as $cost) {
                if (is_array($cost) && isset($cost['resource'], $cost['count'])) {
                    $costs[] = [
                        'resource' => (string) $cost['resource'],
                        'count' => (int) $cost['count'],
                    ];
                }
            }
        }

        $group = $data['group'] ?? 0;
        $normalizedGroup = is_int($group) || (is_string($group) && ctype_digit($group))
            ? (int) $group
            : (string) $group;

        return new ProductionRecipe(
            name: (string) ($data['name'] ?? ''),
            group: $normalizedGroup,
            durationSeconds: (int) ($data['duration_seconds'] ?? 0),
            buffType: (string) ($data['buff_type'] ?? 'Timed'),
            requiresUpgradeLevelMin: (int) ($data['requires_upgrade_level_min'] ?? 0),
            requiresUpgradeLevelMax: (int) ($data['requires_upgrade_level_max'] ?? 99),
            requiresEvent: isset($data['requires_event']) && $data['requires_event'] !== '' ? (string) $data['requires_event'] : null,
            requiresQuest: isset($data['requires_quest']) && $data['requires_quest'] !== '' ? (string) $data['requires_quest'] : null,
            costs: $costs,
            costsKnown: (bool) ($data['costs_known'] ?? (count($costs) > 0)),
            label: isset($data['label']) && $data['label'] !== '' ? (string) $data['label'] : null,
        );
    }
}
