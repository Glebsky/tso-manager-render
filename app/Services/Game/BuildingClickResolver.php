<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\BuildingClickMode;
use App\Exceptions\BuildingNotClickableException;

final readonly class BuildingClickResolver
{
    public function __construct(private ClickableBuildingRegistry $registry) {}

    /**
     * @throws BuildingNotClickableException if Collectible is explicitly requested but building is not in allow-list
     */
    public function resolve(
        BuildingClickMode $requested,
        int $grid,
        string $buildingName,
    ): BuildingClickDecision {
        $kind = $this->registry->classify($buildingName);
        $isClickable = $kind !== null;

        return match ($requested) {
            BuildingClickMode::Auto => $isClickable
                ? new BuildingClickDecision(
                    mode: BuildingClickMode::Collectible,
                    grid: $grid,
                    buildingName: $buildingName,
                    kind: $kind,
                    reason: 'allowlist_match',
                )
                : new BuildingClickDecision(
                    mode: BuildingClickMode::QuestTrigger,
                    grid: $grid,
                    buildingName: $buildingName,
                    kind: null,
                    reason: 'auto_fallback_safe',
                ),

            BuildingClickMode::Collectible => $isClickable
                ? new BuildingClickDecision(
                    mode: BuildingClickMode::Collectible,
                    grid: $grid,
                    buildingName: $buildingName,
                    kind: $kind,
                    reason: 'explicit_allowlist_match',
                )
                : throw new BuildingNotClickableException($buildingName),

            BuildingClickMode::QuestTrigger => new BuildingClickDecision(
                mode: BuildingClickMode::QuestTrigger,
                grid: $grid,
                buildingName: $buildingName,
                kind: null,
                reason: 'explicit_quest_trigger',
            ),
        };
    }
}
