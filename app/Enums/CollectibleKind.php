<?php

declare(strict_types=1);

namespace App\Enums;

enum CollectibleKind: int
{
    case Normal = 0;
    case Event = 1;
}
