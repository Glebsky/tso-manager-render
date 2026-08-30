<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Models\Account;
use App\Services\TsoAmfService;

final readonly class AmfProductionCommandGateway implements ProductionCommandGatewayInterface
{
    public function __construct(
        private TsoAmfService $amf
    ) {}

    public function queueOrder(
        Account $account,
        int $grid,
        int $productionType,
        string $recipeName,
        int $amount,
        int $stacks,
    ): string {
        return $this->amf->queueTimedProduction(
            account: $account,
            grid: $grid,
            productionType: $productionType,
            typeString: $recipeName,
            amount: $amount,
            stacks: $stacks,
        );
    }
}
