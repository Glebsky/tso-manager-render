<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Exceptions\FriendBuildingNotFoundException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\FriendZoneLoadException;
use App\Models\Account;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;

final class ApplyBuffHandler implements TaskActionHandlerInterface
{
    public function __construct(
        private readonly TsoAmfService $amfService,
        private readonly ZoneParserService $zoneParser,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'apply_buff';
    }

    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);
        $uniqueId1 = (int) ($payload['unique_id1'] ?? 0);
        $uniqueId2 = (int) ($payload['unique_id2'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 1);
        $targetScope = (string) ($payload['target_scope'] ?? 'self');
        $targetPlayerId = $payload['target_player_id'] ?? null;

        if ($targetScope === 'friend') {
            $zoneData = $account->zone_data ? json_decode($account->zone_data, true) : [];
            $friends = $zoneData['friends'] ?? [];
            $friend = null;
            $targetPlayerIdInt = (int) $targetPlayerId;

            foreach ($friends as $f) {
                if (isset($f['id']) && (int) $f['id'] === $targetPlayerIdInt) {
                    $friend = $f;
                    break;
                }
            }

            if (! $friend) {
                throw new FriendNotFoundException;
            }

            $friendZoneAmf = $this->amfService->getZone($account, $targetPlayerIdInt);
            $friendZoneData = $this->zoneParser->parse($friendZoneAmf);
            $err = (int) ($friendZoneData['errorCode'] ?? 0);

            if ($err !== 0) {
                $errMsg = GameErrorResolver::getMessage($err);
                throw new FriendZoneLoadException($err, $errMsg);
            }

            $buildings = $friendZoneData['buildings'] ?? [];
            $gridFound = false;

            foreach ($buildings as $building) {
                if (($building['buildingGrid'] ?? null) == $grid) {
                    $gridFound = true;
                    break;
                }
            }

            if (! $gridFound) {
                $friendName = $friend['username'] ?? $friend['nickname'] ?? $payload['target_player_name'] ?? 'Unknown';
                throw new FriendBuildingNotFoundException($grid, (string) $friendName);
            }

            return $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount, $targetPlayerIdInt);
        }

        return $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount);
    }
}
