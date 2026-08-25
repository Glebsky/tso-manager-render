<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Services\Game\Mines\Contracts\MineCatalogInterface;

final readonly class ConfigMineCatalog implements MineCatalogInterface
{
    /** @var list<MineDefinition> */
    private array $definitions;

    /**
     * @param  array<string, array{mine: string, number: int, max_level: int}>  $config
     */
    public function __construct(array $config)
    {
        $definitions = [];
        foreach ($config as $depositName => $entry) {
            $definitions[] = new MineDefinition(
                depositName: (string) $depositName,
                buildingName: $entry['mine'],
                buildingNumber: $entry['number'],
                maxUpgradeLevel: $entry['max_level'],
            );
        }
        $this->definitions = $definitions;
    }

    public function findByDeposit(string $depositName): ?MineDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->depositName === $depositName) {
                return $definition;
            }
        }

        return null;
    }

    public function findByBuilding(string $buildingName): ?MineDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->buildingName === $buildingName) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return list<MineDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
