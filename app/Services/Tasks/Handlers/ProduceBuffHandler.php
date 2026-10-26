<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Enums\ProductionRejectionReason;
use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\Commands\AccountRewardProductionLock;
use App\Services\Game\Commands\CommandResponseDecoder;
use App\Services\Game\Production\ProductionCommandGatewayInterface;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Services\Game\Production\ZoneSnapshotProviderInterface;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\ResultAwareTaskActionHandlerInterface;
use App\Services\Tasks\Execution\TaskActionResult;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

final readonly class ProduceBuffHandler implements ResultAwareTaskActionHandlerInterface
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
        return $this->handleResult($account, $payload)->message;
    }

    /** @param array<string,mixed> $payload */
    public function handleResult(Account $account, array $payload): TaskActionResult
    {
        return (new AccountRewardProductionLock)->run(
            $account->id,
            fn (): TaskActionResult => $this->executeOrder($account, $payload),
            TaskActionResult::skipped(__('tasks.produce_buff.lock_busy')),
            TaskActionResult::failed(__('tasks.produce_buff.unknown_outcome', ['grid' => $payload['grid'] ?? 0, 'recipe' => $payload['recipe_name'] ?? ''])),
        );
    }

    /** @param array<string,mixed> $payload */
    private function executeOrder(Account $account, array $payload): TaskActionResult
    {
        $grid = (int) ($payload['grid'] ?? 0);
        $productionType = isset($payload['production_type']) ? (int) $payload['production_type'] : -1;
        $recipeName = (string) ($payload['recipe_name'] ?? '');
        $amount = (int) ($payload['amount'] ?? 1);
        $stacks = (int) ($payload['stacks'] ?? 1);

        if ($grid <= 0 || $productionType < 0 || $recipeName === '' || $amount < 1 || $amount > 25 || $stacks < 1 || $stacks > 200) {
            throw new InvalidTaskTypeException('Invalid produce_buff parameters.', 422);
        }

        $snapshot = $productionType === 2
            ? $this->zones->forAccount($account, true)
            : $this->zones->forAccount($account);
        $initialCode = $snapshot->raw()['errorCode'] ?? null;
        if ($productionType === 2 && ($snapshot->raw()['server_response_confirmed'] ?? false) === true
            && is_int($initialCode) && in_array($initialCode, self::SESSION_ERROR_CODES, true)) {
            throw new GameServerErrorException($initialCode, GameErrorResolver::getMessage($initialCode));
        }
        $decision = $this->policy->decide($snapshot, $grid, $productionType, $recipeName, $amount, $stacks);
        $postSend = false;
        try {
            if ($productionType === 2) {
                if (($snapshot->raw()['server_response_confirmed'] ?? false) !== true || ($snapshot->raw()['errorCode'] ?? null) !== 0) {
                    return $this->rejected('queue_data_unavailable', $grid, $recipeName, $productionType);
                }
                // A full ready queue or insufficient new-order resources must not prevent collection.
                if ($decision->reason !== null && ! in_array($decision->reason, [ProductionRejectionReason::QueueFull, ProductionRejectionReason::InsufficientResources, ProductionRejectionReason::ResourceDataUnavailable], true)) {
                    return $this->rejected($decision->reason->value, $grid, $recipeName, $productionType);
                }
                $queue = $snapshot->productionQueueFor(2);
                if ($queue === null) {
                    return $this->rejected('queue_data_unavailable', $grid, $recipeName, $productionType);
                }
                if ($queue->hasOrders()) {
                    if (! $queue->headReadyForDelivery()) {
                        return TaskActionResult::skipped(__('tasks.produce_buff.busy'));
                    }
                    $postSend = true;
                    $code = $this->extractErrorCode($this->gateway->deliverOrder($account, 2));
                    if ($code !== 0) {
                        return TaskActionResult::failed(__('tasks.produce_buff.delivery_unconfirmed'));
                    }
                    $snapshot = $this->zones->forAccount($account, true);
                    $queue = $snapshot->productionQueueFor(2);
                    if (($snapshot->raw()['server_response_confirmed'] ?? false) !== true || ($snapshot->raw()['errorCode'] ?? null) !== 0 || $queue === null || $queue->hasOrders()) {
                        return TaskActionResult::failed(__('tasks.produce_buff.delivery_unconfirmed'));
                    }
                    $decision = $this->policy->decide($snapshot, $grid, $productionType, $recipeName, $amount, $stacks);
                }
            }

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

                return $this->rejected($reasonKey, $grid, $recipeName, $productionType);
            }

            $postSend = true;
            $rawAmf = $this->gateway->queueOrder($account, $grid, $productionType, $recipeName, $amount, $stacks);
            $responseCode = $this->extractErrorCode($rawAmf);

            if ($responseCode === 0) {
                if ($productionType === 2 && ! $this->confirmBookbinderOrder($account, $recipeName, $amount, $stacks)) {
                    return TaskActionResult::failed(__('tasks.produce_buff.enqueue_unconfirmed'));
                }

                return TaskActionResult::completed(__('tasks.produce_buff.queued', [
                    'recipe' => $recipeName,
                    'amount' => $amount,
                    'grid' => $grid,
                ]));
            }

            if ($responseCode === null || in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
                Log::warning(sprintf(
                    '[ProduceBuff] Account #%d: grid %d recipe %s returned session code %d after command was sent. Outcome unknown, NOT retrying.',
                    $account->id,
                    $grid,
                    $recipeName,
                    $responseCode ?? -1,
                ));

                return TaskActionResult::failed(__('tasks.produce_buff.unknown_outcome', [
                    'grid' => $grid,
                    'recipe' => $recipeName,
                ]));
            }

            Log::warning("[ProduceBuff] Account #{$account->id}: grid {$grid} recipe {$recipeName} failed with game error {$responseCode}");

            return TaskActionResult::failed(__('tasks.produce_buff.game_error', [
                'message' => GameErrorResolver::getMessage($responseCode),
            ]));
        } catch (Throwable $e) {
            if (! $postSend) {
                throw $e;
            }
            Log::warning('[ProduceBuff] Post-send outcome unknown; command will not be retried.', ['account_id' => $account->id]);

            return TaskActionResult::failed(__('tasks.produce_buff.unknown_outcome', ['grid' => $grid, 'recipe' => $recipeName]));
        }
    }

    /** A transport ACK is not proof that the game accepted an order. Never re-send here. */
    private function confirmBookbinderOrder(Account $account, string $recipe, int $amount, int $stacks): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $snapshot = $this->zones->forAccount($account, true);
            $queue = $snapshot->productionQueueFor(2);
            if (($snapshot->raw()['server_response_confirmed'] ?? false) !== true
                || ($snapshot->raw()['errorCode'] ?? null) !== 0 || $queue === null) {
                return false;
            }
            if ($queue->hasOrders()) {
                $head = $queue->orders[0];

                return $queue->used() === 1 && $head['type_string'] === $recipe
                    && $head['amount'] === $amount && $head['stacks'] === $stacks;
            }
            if ($attempt < 2) {
                Sleep::for(1)->seconds();
            }
        }

        return false;
    }

    private function rejected(string $reason, int $grid, string $recipe, int $type): TaskActionResult
    {
        $message = __('tasks.produce_buff.rejected.'.$reason, compact('grid', 'recipe', 'type'));

        return in_array($reason, ['insufficient_resources', 'queue_full', 'building_upgrading'], true)
            ? TaskActionResult::skipped($message)
            : TaskActionResult::failed($message);
    }

    private function extractErrorCode(string $rawAmf): ?int
    {
        return (new CommandResponseDecoder($this->zoneParser))->errorCode($rawAmf);
    }
}
