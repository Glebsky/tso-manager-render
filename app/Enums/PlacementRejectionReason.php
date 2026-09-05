<?php

declare(strict_types=1);

namespace App\Enums;

enum PlacementRejectionReason: string
{
    case Ok = 'ok';
    case NoDepositAtGrid = 'no_deposit_at_grid';
    case UnknownDepositType = 'unknown_deposit_type';
    case DepositEmpty = 'deposit_empty';
    case GridOccupied = 'grid_occupied';
    case DepositNotAccessible = 'deposit_not_accessible';
    case DepositTypeMismatch = 'deposit_type_mismatch';
    case BuildQueueFull = 'build_queue_full';
}
