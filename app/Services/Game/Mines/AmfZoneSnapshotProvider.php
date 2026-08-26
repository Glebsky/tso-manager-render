<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\GameErrorResolver;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;

final readonly class AmfZoneSnapshotProvider implements ZoneSnapshotProviderInterface
{
    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $zones,
    ) {}

    /**
     * @throws GameServerErrorException
     * @throws \Exception
     * @throws \Exception
     */
    public function forAccount(Account $account): ZoneSnapshot
    {
        $zoneAmf = $this->amf->getZone($account);
        $zone = $this->zones->parse($zoneAmf);

        $errorCode = (int) ($zone['errorCode'] ?? 0);
        if ($errorCode !== 0) {
            throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
        }

        $depositsByGrid = [];
        if (isset($zone['deposits']) && is_array($zone['deposits'])) {
            foreach ($zone['deposits'] as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $grid = (int) ($d['grid'] ?? 0);
                if ($grid <= 0) {
                    continue;
                }

                $depositsByGrid[$grid] = new DepositSnapshot(
                    grid: $grid,
                    name: (string) ($d['name'] ?? ''),
                    amount: (int) ($d['amount'] ?? 0),
                    maxAmount: (int) ($d['max_amount'] ?? 0),
                    accessible: isset($d['accessible']) ? (int) $d['accessible'] : null,
                );
            }
        }

        $buildingsByGrid = [];
        if (isset($zone['buildings']) && is_array($zone['buildings'])) {
            foreach ($zone['buildings'] as $b) {
                if (! is_array($b)) {
                    continue;
                }
                $grid = (int) ($b['buildingGrid'] ?? $b['grid'] ?? 0);
                if ($grid <= 0) {
                    continue;
                }

                $name = (string) ($b['buildingName_string'] ?? $b['buildingName'] ?? '');
                $level = (int) ($b['upgradeLevel'] ?? $b['level'] ?? 0);
                $isProductionActive = (bool) ($b['isProductionActive'] ?? false);
                $upgradeInProgress = (bool) ($b['upgradeIsInProgress'] ?? false);

                $buildingsByGrid[$grid] = new BuildingSnapshot(
                    grid: $grid,
                    name: $name,
                    upgradeLevel: $level,
                    isProductionActive: $isProductionActive,
                    upgradeInProgress: $upgradeInProgress,
                );
            }
        }

        $buildQueue = null;
        if (isset($zone['build_queue']) && is_array($zone['build_queue'])) {
            $buildQueue = new BuildQueueSnapshot(
                used: (int) ($zone['build_queue']['used'] ?? 0),
                total: (int) ($zone['build_queue']['total'] ?? 0),
            );
        }

        return new ZoneSnapshot(
            depositsByGrid: $depositsByGrid,
            buildingsByGrid: $buildingsByGrid,
            buildQueue: $buildQueue,
        );
    }
}
