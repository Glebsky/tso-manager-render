<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionRejectionReason: string
{
    case BuildingNotFound = 'building_not_found';
    case NotAProducer = 'not_a_producer';
    case ProductionTypeUnsupported = 'production_type_unsupported';
    case ProductionTypeMismatch = 'production_type_mismatch';
    case RecipeUnknown = 'recipe_unknown';
    case AmountExceedsRecipeLimit = 'amount_exceeds_recipe_limit';
    case StacksExceedsRecipeLimit = 'stacks_exceeds_recipe_limit';
    case RecipeLevelLocked = 'recipe_level_locked';
    case RecipeRequiresInactiveEvent = 'recipe_requires_inactive_event';
    case BuildingUpgrading = 'building_upgrading';
    case QueueDataUnavailable = 'queue_data_unavailable';
    case QueueFull = 'queue_full';
    case InsufficientResources = 'insufficient_resources';
}
