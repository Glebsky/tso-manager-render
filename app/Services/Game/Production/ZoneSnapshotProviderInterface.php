<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Models\Account;
use App\Support\Zone\ZoneSnapshot;

interface ZoneSnapshotProviderInterface
{
    public function forAccount(Account $account, bool $forceRefresh = false): ZoneSnapshot;
}
