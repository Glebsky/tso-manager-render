<?php

declare(strict_types=1);

namespace App\Services\Game\Mines\Contracts;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Mines\ZoneSnapshot;

interface ZoneSnapshotProviderInterface
{
    /**
     * @throws GameServerErrorException если зона вернула errorCode != 0
     */
    public function forAccount(Account $account, bool $forceRefresh = false): ZoneSnapshot;
}
