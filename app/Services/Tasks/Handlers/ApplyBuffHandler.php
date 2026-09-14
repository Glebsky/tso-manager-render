<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Exceptions\BuffUnavailableException;
use App\Exceptions\FriendBuildingNotFoundException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\FriendZoneLoadException;
use App\Models\Account;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\Tasks\Execution\BuffAvailabilityManager;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class ApplyBuffHandler implements TaskActionHandlerInterface
{
    public function __construct(
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
        private BuffAvailabilityManager $buffAvailabilityManager,
        private CacheRepository $cache,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'apply_buff';
    }

    /**
     * @throws BuffUnavailableException
     * @throws FriendBuildingNotFoundException
     * @throws FriendNotFoundException
     * @throws FriendZoneLoadException
     * @throws \Exception
     */
    public function handle(Account $account, array $payload): string
    {
        $acquiredBuff = $this->buffAvailabilityManager->checkAndAcquire($account, $payload);
        $uniqueId1 = $acquiredBuff->uniqueId1;
        $uniqueId2 = $acquiredBuff->uniqueId2;

        $grid = (int) ($payload['grid'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 1);
        $targetScope = (string) ($payload['target_scope'] ?? 'self');
        $targetPlayerId = $payload['target_player_id'] ?? null;

        if ($targetScope === 'friend') {
            $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
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
                $friend = $this->refreshFriendFromLiveServer($account, $targetPlayerIdInt);
            }

            if (! $friend) {
                throw new FriendNotFoundException;
            }

            $friendZoneCacheKey = "friend-zone:exec:{$account->id}:{$targetPlayerIdInt}";
            /** @var array<string, mixed>|null $friendZoneData */
            $friendZoneData = $this->cache->get($friendZoneCacheKey);

            if (! is_array($friendZoneData)) {
                $friendZoneAmf = $this->amfService->getZone($account, $targetPlayerIdInt);
                $friendZoneData = $this->zoneParser->parse($friendZoneAmf);
                $err = (int) ($friendZoneData['errorCode'] ?? 0);

                if ($err !== 0) {
                    $errMsg = GameErrorResolver::getMessage($err);
                    throw new FriendZoneLoadException($err, $errMsg);
                }

                $this->cache->put($friendZoneCacheKey, $friendZoneData, 180);
            }

            $buildings = $friendZoneData['buildings'] ?? [];
            $gridFound = false;

            foreach ($buildings as $building) {
                if ((int) ($building['buildingGrid'] ?? 0) === $grid) {
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

    /**
     * Attempt to refresh the friends list from the game server if a friend was not found in cache.
     *
     * @return array<string, mixed>|null
     */
    private function refreshFriendFromLiveServer(Account $account, int $targetPlayerIdInt): ?array
    {
        try {
            $rawFriendsAmf = $this->amfService->getFriendList($account);
            $friendsData = $this->zoneParser->parse($rawFriendsAmf);

            $errorCode = (int) ($friendsData['errorCode'] ?? 0);
            if ($errorCode !== 0 || ! isset($friendsData['friends']) || ! is_array($friendsData['friends'])) {
                return null;
            }

            $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
            $ownerUid = $zoneData['userID'] ?? null;
            $friendsList = [];
            $foundFriend = null;

            foreach ($friendsData['friends'] as $p) {
                $puid = $p['id'] ?? $p['userID'] ?? null;
                if ($puid !== null && $ownerUid !== null && (int) $puid === (int) $ownerUid) {
                    continue;
                }
                $item = [
                    'id' => $puid,
                    'username' => $p['username_string'] ?? $p['username'] ?? $p['nickname'] ?? 'Unknown',
                    'nickname' => $p['nickname'] ?? $p['username_string'] ?? $p['username'] ?? 'Unknown',
                    'playerLevel' => $p['playerLevel'] ?? $p['level'] ?? 1,
                    'level' => $p['playerLevel'] ?? $p['level'] ?? 1,
                    'avatarId' => $p['avatarId'] ?? 1,
                    'friendSince' => $p['friendSince'] ?? null,
                ];
                $friendsList[] = $item;

                if ($puid !== null && (int) $puid === $targetPlayerIdInt) {
                    $foundFriend = $item;
                }
            }

            $zoneData['friends'] = $friendsList;
            $account->update([
                'zone_data' => json_encode($zoneData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);

            return $foundFriend;
        } catch (\Throwable) {
            return null;
        }
    }
}
