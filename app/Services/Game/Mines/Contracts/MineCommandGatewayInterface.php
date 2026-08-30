<?php

declare(strict_types=1);

namespace App\Services\Game\Mines\Contracts;

use App\Models\Account;

interface MineCommandGatewayInterface
{
    /** Команда 50. Возвращает сырой ответ игры. */
    public function buildMine(Account $account, int $buildingNumber, int $grid): string;

    /** Команда 60. Возвращает сырой ответ игры. */
    public function upgradeMine(Account $account, int $grid): string;
}
