<?php

declare(strict_types=1);

namespace App\Enums;

enum BuildingClickMode: string
{
    case Auto = 'auto';
    case Collectible = 'collectible';
    case QuestTrigger = 'quest_trigger';
}
