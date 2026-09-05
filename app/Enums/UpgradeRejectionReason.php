<?php

declare(strict_types=1);

namespace App\Enums;

enum UpgradeRejectionReason: string
{
    case Ok = 'ok';
    case NoBuildingAtGrid = 'no_building_at_grid';
    case NotAMine = 'not_a_mine';
    case MineDepleted = 'mine_depleted';
    case BuildingUnderConstruction = 'building_under_construction';
    case MaxLevelReached = 'max_level_reached';
    case UpgradeAlreadyInProgress = 'upgrade_already_in_progress';
    case ProductionInactive = 'production_inactive';
    case BuildQueueFull = 'build_queue_full';
}
