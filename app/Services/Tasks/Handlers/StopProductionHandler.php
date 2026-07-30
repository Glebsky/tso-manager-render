<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Models\Account;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;

final class StopProductionHandler implements TaskActionHandlerInterface
{
    public function __construct(private readonly TsoAmfService $amfService) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'stop_production';
    }

    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);

        return $this->amfService->stopProduction($account, $grid);
    }
}
