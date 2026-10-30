<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Zone\ZoneCacheInvalidator;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Task actions run through the central {@see GameSessionGuard}; this class only
 * adds the task-specific retry for a stale friend visitor session (550).
 */
final readonly class ActionRetryPolicy
{
    public function __construct(
        private GameSessionGuard $guard,
        private ZoneCacheInvalidator $zoneCache,
    ) {}

    /**
     * @param  callable(): string  $action
     *
     * @throws Exception
     */
    public function execute(Account $account, string $taskTypeStr, callable $action): string
    {
        $accountId = $account->id;

        return $this->guard->run(
            $account,
            "Action [{$taskTypeStr}]",
            $action,
            function (GameServerErrorException $e, int $attempt) use ($taskTypeStr, $accountId): bool {
                // 550 (Invalid value provided / visitor session expired):
                if ($attempt !== 1 || (int) $e->getCode() !== 550 || $taskTypeStr !== 'apply_buff') {
                    return false;
                }

                Log::info("[TaskExecution] Action [{$taskTypeStr}] hit game error 550 (visitor session expired / invalid value) for account #{$accountId}; invalidating friend zone cache and retrying in 1s");
                $this->zoneCache->invalidateFriendZones($accountId);
                Sleep::for(1)->seconds();

                return true;
            },
        );
    }
}
