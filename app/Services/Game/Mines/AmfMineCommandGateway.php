<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

use App\Models\Account;
use App\Services\Game\Mines\Contracts\MineCommandGatewayInterface;
use App\Services\TsoAmfService;

final readonly class AmfMineCommandGateway implements MineCommandGatewayInterface
{
    public function __construct(
        private TsoAmfService $amf,
    ) {}

    public function buildMine(Account $account, int $buildingNumber, int $grid): string
    {
        return $this->amf->buildBuilding($account, $buildingNumber, $grid);
    }

    public function upgradeMine(Account $account, int $grid): string
    {
        return $this->amf->upgradeBuilding($account, $grid);
    }
}
