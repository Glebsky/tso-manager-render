<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionRejectionReason: string
{
    case BuildingNotFound = 'building_not_found';
    case NotAProducer = 'not_a_producer';
    case ProductionTypeMismatch = 'production_type_mismatch';
    case QueueDataUnavailable = 'queue_data_unavailable';
    case QueueFull = 'queue_full';
    case RecipeUnknown = 'recipe_unknown';
    case RecipeLevelLocked = 'recipe_level_locked';
    case BuildingUpgrading = 'building_upgrading';
    case InsufficientResources = 'insufficient_resources';
}
