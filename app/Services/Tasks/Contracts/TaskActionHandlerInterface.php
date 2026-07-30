<?php

declare(strict_types=1);

namespace App\Services\Tasks\Contracts;

use App\Models\Account;

interface TaskActionHandlerInterface
{
    public function supports(string $actionType): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Account $account, array $payload): string;
}
