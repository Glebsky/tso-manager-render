<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Paused = 'paused';

    public function isBusy(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
