<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\Mines\Contracts\MineCommandGatewayInterface;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\MineTargetListService;
use App\Services\Game\Mines\MineUpgradePolicy;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class UpgradeMineHandler implements TaskActionHandlerInterface
{
    /** @var list<int> */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private MineUpgradePolicy $policy,
        private MineCommandGatewayInterface $gateway,
        private ZoneParserService $zoneParser,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === TaskType::UpgradeMine->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws GameServerErrorException
     * @throws InvalidTaskTypeException
     */
    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);
        if ($grid <= 0) {
            throw new InvalidTaskTypeException('Invalid building grid parameter.', 422);
        }

        $maxLevel = (int) ($payload['max_level'] ?? 0);

        $zone = $this->zones->forAccount($account);
        $decision = $this->policy->decide($zone, $grid, $maxLevel > 0 ? $maxLevel : null);

        Log::info(sprintf(
            '[UpgradeMine] Account #%d: grid=%d, mine="%s", currentLevel=%d, targetLevel=%d, freeSlots=%d, allowed=%s, reason=%s',
            $account->id,
            $grid,
            $decision->mineName,
            $decision->currentLevel,
            $decision->targetLevel,
            $decision->freeSlots,
            $decision->allowed ? 'yes' : 'no',
            $decision->reason->value,
        ));

        if (! $decision->allowed || $decision->definition === null) {
            return __('tasks.upgrade_mine.rejected.'.$decision->reason->value, [
                'grid' => $grid,
                'name' => $decision->buildingName,
                'level' => $decision->currentLevel,
            ]);
        }

        $rawAmf = $this->gateway->upgradeMine($account, $grid);
        $responseCode = $this->extractErrorCode($rawAmf);

        if ($responseCode === 0) {
            MineTargetListService::clearCache($account->id);

            return __('tasks.upgrade_mine.upgraded', [
                'name' => $decision->mineName,
                'level' => $decision->targetLevel,
                'grid' => $grid,
            ]);
        }

        if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
            Log::warning(sprintf(
                '[UpgradeMine] Account #%d: grid %d returned session code %d after the command was sent. Outcome unknown, NOT retrying.',
                $account->id,
                $grid,
                $responseCode,
            ));
            MineTargetListService::clearCache($account->id);

            return __('tasks.upgrade_mine.unknown_outcome', ['grid' => $grid]);
        }

        Log::warning("[UpgradeMine] Account #{$account->id}: grid {$grid} failed with game error {$responseCode}");

        return __('tasks.upgrade_mine.game_error', ['message' => GameErrorResolver::getMessage($responseCode)]);
    }

    private function extractErrorCode(string $rawAmf): int
    {
        try {
            $parsed = $this->zoneParser->parse($rawAmf);

            return (int) ($parsed['errorCode'] ?? 0);
        } catch (Throwable) {
            Log::warning('[UpgradeMine] unparseable response');

            return 0;
        }
    }
}
