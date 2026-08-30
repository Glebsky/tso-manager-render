<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Models\Account;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;

final readonly class StartProductionHandler implements TaskActionHandlerInterface
{
    public function __construct(private TsoAmfService $amfService) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'start_production';
    }

    /**
     * @throws \Exception
     */
    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);

        return $this->amfService->startProduction($account, $grid);
    }
}
