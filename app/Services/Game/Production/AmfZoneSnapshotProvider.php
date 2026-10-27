<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Zone\HomeZoneRepository;
use App\Support\Zone\ZoneSnapshot;
use Exception;

final readonly class AmfZoneSnapshotProvider implements ZoneSnapshotProviderInterface
{
    public function __construct(
        private HomeZoneRepository $homeZones,
    ) {}

    /**
     * Without $forceRefresh any persisted zone with buildings is used (as
     * before); a live read goes through the guarded, persisted home zone read.
     *
     * @throws GameServerErrorException
     * @throws Exception
     */
    public function forAccount(Account $account, bool $forceRefresh = false): ZoneSnapshot
    {
        if (! $forceRefresh && ! empty($account->zone_data)) {
            $snapshot = ZoneSnapshot::fromData($account->zone_data);
            if ($snapshot->buildingCount() !== null && $snapshot->buildingCount() > 0) {
                return $snapshot;
            }
        }

        return new ZoneSnapshot($this->homeZones->refresh($account));
    }
}
