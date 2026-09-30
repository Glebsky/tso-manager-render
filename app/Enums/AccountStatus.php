<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountStatus: string
{
    case Online = 'online';
    case Active = 'active';
    case Syncing = 'syncing';
    case Error = 'error';
    case SessionExpired = 'session_expired';
}
