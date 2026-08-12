<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dGetFriendsVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerAction;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerCall;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dStartSpecialistTaskVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dUniqueID;
use Exception;

class TsoAmfService
{
    public const CMD_BUILD = 50;

    public const CMD_UPGRADE = 60;

    public const CMD_APPLY_BUFF = 61;

    public const CMD_SET_TASK = 95;

    public const CMD_STOP_PRODUCTION = 107;

    public const CMD_GET_ZONE = 1001;

    public const CMD_EXECUTE_PICKUP = 13002;

    /** COMMAND.DESTRUCT_BUILDING — the command a click on a collectible really sends. */
    public const CMD_DESTRUCT_BUILDING = 65;

    private int $dsoAuthRandomClientID;

    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoClientInterface $client
    ) {
        $this->dsoAuthRandomClientID = mt_rand(0, 2147483646);
    }

    public function setDsId(string $dsId): void
    {
        $this->client->setDsId($dsId);
    }

    public function resetClient(): void
    {
        $this->client->resetClients();
    }

    private function buildServerCall(Account $account, int $type, mixed $actionData, ?int $targetZoneId = null): defaultGame_Communication_VO_dServerCall
    {
        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = $account->dso_auth_token;
        $call->dsoAuthUser = (int) $account->dso_auth_user;
        $call->zoneID = $targetZoneId ?? (int) $account->dso_auth_user;
        $call->type = $type;
        $call->dsoAuthRandomClientID = $this->dsoAuthRandomClientID;
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

    private function sendServerCall(Account $account, int $commandType, mixed $actionData, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
    {
        $zoneId = $targetZoneId ?? 0;
        $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

        try {
            return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            if (str_contains($errorMsg, 'Load Server') || str_contains($errorMsg, '301') || str_contains($errorMsg, 'HTTP 500') || str_contains($errorMsg, 'HTTP 401') || str_contains($errorMsg, 'HTTP 403')) {
                try {
                    $this->authService->login($account);
                    $account->refresh();
                    $this->resetClient();

                    $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

                    return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
                } catch (Exception $retryException) {
                    throw new Exception($e->getMessage().' (Auto-relogin also failed: '.$retryException->getMessage().')');
                }
            }

            throw $e;
        }
    }

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

    public function getFriendList(Account $account): string
    {
        $getFriends = new defaultGame_Communication_VO_dGetFriendsVO;
        $getFriends->version = 'fe5e82453230b4145854f220221b9360f33dec92';

        return $this->sendServerCall(
            $account,
            1014,
            $getFriends,
            'PLAYER',
            'GetFriends',
            'com.bluebyte.game.servlet.PlayerHandler'
        );
    }

    public function stopProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(0, $grid, 0, null);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

    public function startProduction(Account $account, int $grid): string
    {
        $action = $this->buildServerAction(1, $grid, 0, null);

        return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
    }

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
     */
    public function collectCollectible(Account $account, int $grid, string $buildingClass = 'cCollectibleBuilding'): string
    {
        $action = $this->buildServerAction(0, $grid, 0, $buildingClass);

        return $this->sendServerCall($account, self::CMD_DESTRUCT_BUILDING, $action);
    }

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
}
