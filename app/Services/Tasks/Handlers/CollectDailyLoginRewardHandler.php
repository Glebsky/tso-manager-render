<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Enums\TaskType;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\Commands\AccountRewardProductionLock;
use App\Services\Game\Commands\CommandResponseDecoder;
use App\Services\Game\Quests\DailyLoginQuestSelector;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\ResultAwareTaskActionHandlerInterface;
use App\Services\Tasks\Execution\TaskActionResult;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CollectDailyLoginRewardHandler implements ResultAwareTaskActionHandlerInterface
{
    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $parser,
        private DailyLoginQuestSelector $selector,
        private CommandResponseDecoder $responses,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === TaskType::CollectDailyLoginReward->value;
    }

    /** @param array<string,mixed> $payload */
    public function handle(Account $account, array $payload): string
    {
        return $this->handleResult($account, $payload)->message;
    }

    /** @param array<string,mixed> $payload */
    public function handleResult(Account $account, array $payload): TaskActionResult
    {
        if (($payload['scope'] ?? null) !== 'self') {
            throw new InvalidTaskTypeException('Daily login reward requires scope=self.', 422);
        }

        return (new AccountRewardProductionLock)->run(
            $account->id,
            fn (): TaskActionResult => $this->collect($account),
            TaskActionResult::skipped(__('tasks.daily_login.busy')),
            TaskActionResult::failed(__('tasks.daily_login.unknown_outcome')),
        );
    }

    private function collect(Account $account): TaskActionResult
    {
        // Safe to retry only these pre-send reads. Never use a cached quest pool.
        $data = $this->parser->parse($this->amf->getLatestQuestList($account));
        $code = $this->readErrorCode($data);
        if (in_array($code, [1005, 1012], true)) {
            throw new GameServerErrorException($code, GameErrorResolver::getMessage($code));
        }
        $quests = $code === 0 ? $this->selector->quests($data) : null;
        if ($quests === null) {
            return TaskActionResult::failed(__('tasks.daily_login.unavailable'));
        }
        $candidates = array_values(array_filter($quests, $this->selector->isClaimable(...)));
        if ($candidates === []) {
            return TaskActionResult::skipped(__('tasks.daily_login.none_available'));
        }
        if (count($candidates) !== 1) {
            return TaskActionResult::failed(__('tasks.daily_login.ambiguous'));
        }
        $uid = $this->selector->identity($candidates[0]);
        if ($uid === null) {
            return TaskActionResult::failed(__('tasks.daily_login.unavailable'));
        }
        try {
            // From here onward, no exception may trigger the outer session retry.
            $code = $this->responses->errorCode($this->amf->sendDailyLoginRewardOk($account, $uid[0], $uid[1]));
            if ($code === null || in_array($code, [1005, 1012], true)) {
                return TaskActionResult::failed(__('tasks.daily_login.unknown_outcome'));
            }
            if ($code !== 0) {
                return TaskActionResult::failed(__('tasks.daily_login.game_error', ['message' => GameErrorResolver::getMessage($code)]));
            }
            $confirmation = $this->parser->parse($this->amf->getLatestQuestList($account));
            $latest = $this->readErrorCode($confirmation) === 0 ? $this->selector->quests($confirmation) : null;
            if ($latest === null) {
                return TaskActionResult::failed(__('tasks.daily_login.unknown_outcome'));
            }
            foreach ($latest as $quest) {
                $identity = $this->selector->identity($quest);
                // Only disappearance from a trusted full list confirms collection.
                // An unknown new mode on the same UID is not proof of receipt.
                if ($identity === null || $identity === $uid) {
                    return TaskActionResult::failed(__('tasks.daily_login.unknown_outcome'));
                }
            }

            return TaskActionResult::completed(__('tasks.daily_login.collected'));
        } catch (Throwable) {
            Log::warning('[DailyLogin] Post-send outcome unknown; command will not be retried.', ['account_id' => $account->id]);

            return TaskActionResult::failed(__('tasks.daily_login.unknown_outcome'));
        }
    }

    /** @param array<string,mixed> $data */
    private function readErrorCode(array $data): ?int
    {
        return ($data['server_response_confirmed'] ?? false) === true
            && isset($data['errorCode']) && is_int($data['errorCode']) ? $data['errorCode'] : null;
    }
}
