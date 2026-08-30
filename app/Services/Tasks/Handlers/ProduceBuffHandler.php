<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\Production\ProductionCommandGatewayInterface;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Services\Game\Production\ZoneSnapshotProviderInterface;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ProduceBuffHandler implements TaskActionHandlerInterface
{
    /** @var list<int> */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private ProductionOrderPolicy $policy,
        private ProductionCommandGatewayInterface $gateway,
        private ZoneParserService $zoneParser,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === TaskType::ProduceBuff->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws GameServerErrorException
     * @throws InvalidTaskTypeException
     * @throws Exception
     */
    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);
        $productionType = isset($payload['production_type']) ? (int) $payload['production_type'] : -1;
        $recipeName = (string) ($payload['recipe_name'] ?? '');
        $amount = (int) ($payload['amount'] ?? 1);
        $stacks = (int) ($payload['stacks'] ?? 1);

        if ($grid <= 0 || $productionType < 0 || $recipeName === '' || $amount < 1 || $amount > 25 || $stacks < 1 || $stacks > 200) {
            throw new InvalidTaskTypeException('Invalid produce_buff parameters.', 422);
        }

        $snapshot = $this->zones->forAccount($account);
        $decision = $this->policy->decide($snapshot, $grid, $productionType, $recipeName, $amount);

        Log::info(sprintf(
            '[ProduceBuff] Account #%d: grid=%d, productionType=%d, recipe="%s", amount=%d, stacks=%d, allowed=%s, reason=%s',
            $account->id,
            $grid,
            $productionType,
            $recipeName,
            $amount,
            $stacks,
            $decision->allowed ? 'yes' : 'no',
            $decision->reason !== null ? $decision->reason->value : 'none',
        ));

        if (! $decision->allowed || $decision->reason !== null) {
            $reasonKey = $decision->reason !== null ? $decision->reason->value : 'building_not_found';

            return __('tasks.produce_buff.rejected.'.$reasonKey, [
                'grid' => $grid,
                'recipe' => $recipeName,
                'type' => $productionType,
            ]);
        }

        $rawAmf = $this->gateway->queueOrder($account, $grid, $productionType, $recipeName, $amount, $stacks);
        $responseCode = $this->extractErrorCode($rawAmf);

        if ($responseCode === 0) {
            return __('tasks.produce_buff.queued', [
                'recipe' => $recipeName,
                'amount' => $amount,
                'grid' => $grid,
            ]);
        }

        if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
            Log::warning(sprintf(
                '[ProduceBuff] Account #%d: grid %d recipe %s returned session code %d after command was sent. Outcome unknown, NOT retrying.',
                $account->id,
                $grid,
                $recipeName,
                $responseCode,
            ));

            return __('tasks.produce_buff.unknown_outcome', [
                'grid' => $grid,
                'recipe' => $recipeName,
            ]);
        }

        Log::warning("[ProduceBuff] Account #{$account->id}: grid {$grid} recipe {$recipeName} failed with game error {$responseCode}");

        return __('tasks.produce_buff.game_error', [
            'message' => GameErrorResolver::getMessage($responseCode),
        ]);
    }

    private function extractErrorCode(string $rawAmf): int
    {
        try {
            $parsed = $this->zoneParser->parse($rawAmf);

            return (int) ($parsed['errorCode'] ?? 0);
        } catch (Throwable) {
            Log::warning('[ProduceBuff] unparseable response');

            return 0;
        }
    }
}
