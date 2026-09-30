<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketSyncStatus: string
{
    case NotConfigured = 'not_configured';
    case Syncing = 'syncing';
    case Connected = 'connected';
    case Error = 'error';
    case Disabled = 'disabled';
}
