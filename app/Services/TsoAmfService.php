<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dGetFriendsVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerAction;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerCall;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dStartSpecialistTaskVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dTimedProductionVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dUniqueID;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TsoAmfService
{
    public const int CMD_BUILD = 50;

    public const int CMD_UPGRADE = 60;

    public const int CMD_APPLY_BUFF = 61;

    public const int CMD_START_TIMED_PRODUCTION = 91;

    public const int CMD_SET_TASK = 95;

    public const int CMD_STOP_PRODUCTION = 107;

    public const int CMD_GET_ZONE = 1001;

    public const int CMD_EXECUTE_PICKUP = 13002;

    /** COMMAND.DESTRUCT_BUILDING — the command a click on a collectible really sends. */
    public const int CMD_DESTRUCT_BUILDING = 65;

    /** COMMAND.QUEST_TRIGGER — client_scripts.txt:69425 */
    public const int CMD_QUEST_TRIGGER = 100;

    /** SERVER_STACK_BUILDING_SELECTED — client_scripts.txt:62956 */
    public const int QUEST_STACK_BUILDING_SELECTED = 2;

    /** SERVER_STACK_GET_LATEST_QUEST_LIST — client_scripts.txt:62958 */
    public const int QUEST_STACK_GET_LATEST_QUEST_LIST = 4;

    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoClientInterface $client
    ) {}

    /**
     * Stable per-account client identity.
     *
     * The Flash client generates this once per running client and sends the
     * same value with every call. Generating it per PHP process meant that the
     * scheduler process, the queue worker and every web request each presented
     * themselves to the game server as a different client for the same account,
     * which the server reports as error 1012 (NEWER_SESSION_DETECTED) to whoever
     * is not the current owner of the zone session.
     *
     * Persisting it per account makes all processes look like one client.
     */
    private function clientIdFor(Account $account): int
    {
        return (int) Cache::remember(
            "tso:client_id:{$account->id}",
            now()->addDays(30),
            static fn (): int => random_int(0, 2147483646),
        );
    }

    /**
     * Forget the persisted client identity. Only needed when the account's
     * credentials change, since a fresh identity forces the game server to
     * treat the next call as a brand-new client.
     */
    public function forgetClientId(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;
        Cache::forget("tso:client_id:{$id}");
    }

    public function setDsId(string $dsId, ?int $accountId = null): void
    {
        $this->client->setDsId($dsId, $accountId);
    }

    /**
     * Drop cached in-memory transport state for the current process.
     */
    public function resetClient(?int $accountId = null): void
    {
        $this->client->resetClients($accountId);
    }

    /**
     * Invalidate shared session in cache across all processes for a specific account.
     */
    public function invalidateSession(int $accountId): void
    {
        $this->forgetClientId($accountId);
        $this->client->invalidateSession($accountId);
    }

    /**
     * Ensure the player's zone is loaded and initialized on the game server.
     * Useful to warm up the zone before executing direct actions (like specialist/production)
     * or to recover when game server returns error 1012.
     *
     * @throws Exception
     */
    public function ensureZoneLoaded(Account $account, int $maxAttempts = 3, int $delaySeconds = 2): string
    {
        $lastResponse = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("[TsoAmf] Ensuring zone is loaded for account #{$account->id} (attempt {$attempt}/{$maxAttempts})");
            $lastResponse = $this->getZone($account);

            if (str_contains($lastResponse, '1012')) {
                Log::info("[TsoAmf] Zone still initializing for account #{$account->id} (code 1012); waiting {$delaySeconds}s...");
                if ($attempt < $maxAttempts) {
                    sleep($delaySeconds);
                }

                continue;
            }

            Log::info("[TsoAmf] Zone ready for account #{$account->id} on attempt {$attempt}/{$maxAttempts}");
            break;
        }

        return $lastResponse;
    }

    private function buildServerCall(Account $account, int $type, mixed $actionData, ?int $targetZoneId = null): defaultGame_Communication_VO_dServerCall
    {
        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = $account->dso_auth_token;
        $call->dsoAuthUser = (int) $account->dso_auth_user;
        $call->zoneID = $targetZoneId ?? (int) $account->dso_auth_user;
        $call->type = $type;
        $call->dsoAuthRandomClientID = $this->clientIdFor($account);
        $call->data = $actionData;

        return $call;
    }

    private function buildServerAction(int $type, int $grid, int $endGrid, mixed $data = null): defaultGame_Communication_VO_dServerAction
    {
        $action = new defaultGame_Communication_VO_dServerAction;
        $action->type = $type;
        $action->grid = $grid;
        $action->endGrid = $endGrid;
        $action->data = $data;

        return $action;
    }

    /**
     * @throws Exception
     */
    private function sendServerCall(Account $account, int $commandType, mixed $actionData, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
    {
        $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

        try {
            return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
        } catch (SessionExpiredException $e) {
            Log::info("[TsoAmf] Session rejected for account #{$account->id}; performing a single forced re-login and retry");

            try {
                $this->authService->resetSession($account);
                $this->invalidateSession($account->id);
                $this->resetClient($account->id);
                $this->authService->ensureAuthenticated($account);
                $account->refresh();

                $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

                return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
            } catch (Exception $retryException) {
                throw new \RuntimeException($e->getMessage().' (Auto-relogin also failed: '.$retryException->getMessage().')');
            }
        }
    }

    /**
     * @throws Exception
     */
    public function getZone(Account $account, ?int $targetZoneId = null): string
    {
        $zoneId = $targetZoneId ?? (int) $account->dso_auth_user;

        return $this->sendServerCall(
            $account,
            self::CMD_GET_ZONE,
            false,
            'SMC',
            'ExecuteServerCall',
            'com.bluebyte.game.servlet.EventHandler',
            $zoneId
        );
    }

    /**
     * @throws Exception
     */
    public function getMarketOffers(Account $account): string
    {
        return $this->sendServerCall(
            $account,
            1061,
            null,
            'TRADE',
            'GetAvailableOffers',
            'com.bluebyte.game.servlet.TradeWindowHandler'
        );
    }

    /**
     * @throws Exception
     */
    public function getFriendList(Account $account): string
    {
        $getFriends = new defaultGame_Communication_VO_dGetFriendsVO;
        $getFriends->version = '3c2374c967d4223b5ccb643c0305c8d9eeb0a943';

        return $this->sendServerCall(
            $account,
            1014,
            $getFriends,
            'PLAYER',
            'GetFriends',
            'com.bluebyte.game.servlet.PlayerHandler'
        );
    }

    /**
     * @throws Exception
     */
    public function stopProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(0, $grid, 0);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    /**
     * @throws Exception
     */
    public function startProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(1, $grid, 0);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    /**
     * @throws Exception
     */
    public function buildBuilding(Account $account, int $buildingNumber, int $grid): string
    {
        return $this->sendServerCall(
            $account,
            self::CMD_BUILD,
            $this->buildServerAction($buildingNumber, $grid, 0),
        );
    }

    /**
     * @throws Exception
     */
    public function upgradeBuilding(Account $account, int $grid): string
    {
        return $this->sendServerCall(
            $account,
            self::CMD_UPGRADE,
            $this->buildServerAction(0, $grid, 0),
        );
    }

    /**
     * @throws Exception
     */
    public function applyBuff(Account $account, int $grid, int $uniqueId1, int $uniqueId2, int $amount = 1, ?int $targetZoneId = null): string
    {
        $buffUid = new defaultGame_Communication_VO_dUniqueID;
        $buffUid->uniqueID1 = $uniqueId1;
        $buffUid->uniqueID2 = $uniqueId2;

        $action = $this->buildServerAction(0, $grid, $amount, $buffUid);

        return $this->sendServerCall(
            $account,
            self::CMD_APPLY_BUFF,
            $action,
            'SMC',
            'ExecuteServerCall',
            'com.bluebyte.game.servlet.EventHandler',
            $targetZoneId
        );
    }

    /**
     * Collect a single island collectible (pickup).
     *
     * @throws Exception
     */
    public function executePickup(Account $account, int $uniqueId1, int $uniqueId2): string
    {
        $pickupUid = new defaultGame_Communication_VO_dUniqueID;
        $pickupUid->uniqueID1 = $uniqueId1;
        $pickupUid->uniqueID2 = $uniqueId2;

        return $this->sendServerCall($account, self::CMD_EXECUTE_PICKUP, $pickupUid);
    }

    /**
     * Collect one island collectible by clicking its building.
     *
     * @throws Exception
     */
    public function collectCollectible(Account $account, int $grid, string $buildingClass = 'cCollectibleBuilding'): string
    {
        $action = $this->buildServerAction(0, $grid, 0, $buildingClass);

        return $this->sendServerCall($account, self::CMD_DESTRUCT_BUILDING, $action);
    }

    /**
     * Send a building selected quest trigger (COMMAND.QUEST_TRIGGER = 100).
     *
     * client_scripts.txt:69425 (COMMAND.QUEST_TRIGGER = 100)
     * client_scripts.txt:62956 (SERVER_STACK_BUILDING_SELECTED = 2)
     *
     * Differs from collectCollectible (65): grid is passed inside data,
     * while action.grid and action.endGrid remain 0.
     *
     * @throws Exception
     */
    public function sendBuildingSelectedQuestTrigger(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(self::QUEST_STACK_BUILDING_SELECTED, 0, 0, $grid);

        return $this->sendServerCall(
            $account,
            self::CMD_QUEST_TRIGGER,
            $action,
            targetZoneId: (int) $account->dso_auth_user,
        );
    }

    /**
     * Fetch the latest quest list / pool from server (COMMAND.QUEST_TRIGGER = 100, type = 4).
     *
     * client_scripts.txt:69425 (COMMAND.QUEST_TRIGGER = 100)
     * client_scripts.txt:62958 (SERVER_STACK_GET_LATEST_QUEST_LIST = 4)
     *
     * @throws Exception
     */
    public function getLatestQuestList(Account $account): string
    {
        $action = $this->buildServerAction(self::QUEST_STACK_GET_LATEST_QUEST_LIST, 0, 0);

        return $this->sendServerCall(
            $account,
            self::CMD_QUEST_TRIGGER,
            $action,
            targetZoneId: (int) $account->dso_auth_user,
        );
    }

    /**
     * @throws Exception
     */
    public function sendSpecialist(Account $account, int $taskType, int $subTaskId, int $uniqueId1, int $uniqueId2): string
    {
        $specUid = new defaultGame_Communication_VO_dUniqueID;
        $specUid->uniqueID1 = $uniqueId1;
        $specUid->uniqueID2 = $uniqueId2;

        $taskVo = new defaultGame_Communication_VO_dStartSpecialistTaskVO;
        $taskVo->uniqueID = $specUid;
        $taskVo->subTaskID = $subTaskId;
        $taskVo->paramString = '';

        $action = $this->buildServerAction($taskType, 0, 0, $taskVo);

        return $this->sendServerCall($account, self::CMD_SET_TASK, $action);
    }

    /**
     * Places an order into a building's production queue.
     *
     * In contrast to other commands, payload is the dTimedProductionVO itself without dServerAction.
     * Source: client_scripts.txt:307959-307965, protocol-evidence.md §2.
     *
     * @throws Exception
     */
    public function queueTimedProduction(
        Account $account,
        int $grid,
        int $productionType,
        string $typeString,
        int $amount = 1,
        int $stacks = 1,
    ): string {
        $vo = new defaultGame_Communication_VO_dTimedProductionVO;
        $vo->productionType = $productionType;
        $vo->type_string = $typeString;
        $vo->amount = $amount;
        $vo->stacks = $stacks;
        $vo->buildingGrid = $grid;

        return $this->sendServerCall($account, self::CMD_START_TIMED_PRODUCTION, $vo);
    }
}
