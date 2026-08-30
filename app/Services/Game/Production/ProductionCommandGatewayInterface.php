<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Models\Account;

interface ProductionCommandGatewayInterface
{
    /**
     * @return string raw AMF response from game server
     */
    public function queueOrder(
        Account $account,
        int $grid,
        int $productionType,
        string $recipeName,
        int $amount,
        int $stacks,
    ): string;
}
