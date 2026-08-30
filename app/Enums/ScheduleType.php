<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleType: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Interval = 'interval';

    public function isOnce(): bool
    {
        return $this === self::Once;
    }
}
