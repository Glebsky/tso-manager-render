<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Models\Account;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;

final readonly class SendSpecialistHandler implements TaskActionHandlerInterface
{
    public function __construct(private TsoAmfService $amfService) {}

    public function supports(string $actionType): bool
    {
        return in_array($actionType, ['send_geologist', 'send_explorer', 'send_specialist'], true);
    }

    /**
     * @throws \Exception
     */
    public function handle(Account $account, array $payload): string
    {
        $taskTypeVal = (int) ($payload['task_type'] ?? 0);
        $subTaskId = (int) ($payload['sub_task_id'] ?? 0);
        $uniqueId1 = (int) ($payload['unique_id1'] ?? 0);
        $uniqueId2 = (int) ($payload['unique_id2'] ?? 0);

        return $this->amfService->sendSpecialist($account, $taskTypeVal, $subTaskId, $uniqueId1, $uniqueId2);
    }
}
