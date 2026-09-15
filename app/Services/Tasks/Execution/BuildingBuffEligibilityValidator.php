<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\BuildingNotBuffableException;

final class BuildingBuffEligibilityValidator
{
    /**
     * Validate whether the building at $grid in the given zone dataset is eligible to receive a buff.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $zoneOrBuildings
     * @return array<string, mixed> The validated building data
     *
     * @throws BuildingNotBuffableException
     */
    public function validate(array $zoneOrBuildings, int $grid, ?string $scopeName = null): array
    {
        /** @var list<array<string, mixed>> $buildings */
        $buildings = isset($zoneOrBuildings['buildings']) && is_array($zoneOrBuildings['buildings'])
            ? $zoneOrBuildings['buildings']
            : (array_is_list($zoneOrBuildings) ? $zoneOrBuildings : []);

        $targetBuilding = null;
        foreach ($buildings as $building) {
            $bGrid = (int) ($building['buildingGrid'] ?? $building['grid'] ?? 0);
            if ($bGrid === $grid) {
                $targetBuilding = $building;
                break;
            }
        }

        if ($targetBuilding === null) {
            throw BuildingNotBuffableException::buildingNotFound($grid, $scopeName);
        }

        $name = (string) ($targetBuilding['buildingName_string'] ?? $targetBuilding['buildingName'] ?? 'Building');

        // 1. Depleted mine check
        if (str_starts_with($name, 'MineDepleted') || str_contains($name, 'Depleted')) {
            throw BuildingNotBuffableException::depleted($grid, $name);
        }

        // 2. Upgrading check
        if (! empty($targetBuilding['upgradeIsInProgress'])) {
            throw BuildingNotBuffableException::upgrading($grid, $name);
        }

        // 3. Stopped production check (in TSO, mode 28 = PRODUCES_NO_RESOURCES)
        $mode = (int) ($targetBuilding['buildingMode'] ?? 0);
        $isActive = $targetBuilding['isProductionActive'] ?? null;
        if ($mode === 28 || $isActive === false) {
            throw BuildingNotBuffableException::productionStopped($grid, $name);
        }

        // 4. Already buffed check
        $buffs = $targetBuilding['buffs'] ?? [];
        if (is_array($buffs) && ! empty($buffs)) {
            $firstBuff = reset($buffs);
            $activeBuffName = is_array($firstBuff)
                ? (string) ($firstBuff['buffName_string'] ?? $firstBuff['name_string'] ?? $firstBuff['buff_name'] ?? '')
                : null;

            throw BuildingNotBuffableException::alreadyBuffed($grid, $name, $activeBuffName ?: null);
        }

        return $targetBuilding;
    }
}
