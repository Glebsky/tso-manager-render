<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Exceptions\BuffUnavailableException;
use App\Exceptions\BuildingNotBuffableException;
use App\Exceptions\FriendBuildingNotFoundException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\FriendZoneLoadException;
use App\Models\Account;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\Tasks\Execution\BuffAvailabilityManager;
use App\Services\Tasks\Execution\BuildingBuffEligibilityValidator;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

final readonly class ApplyBuffHandler implements TaskActionHandlerInterface
{
    private const int FRIEND_ZONE_KEEPALIVE_THRESHOLD = 15;

    public function __construct(
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
        private BuffAvailabilityManager $buffAvailabilityManager,
        private BuildingBuffEligibilityValidator $buildingValidator,
        private CacheRepository $cache,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'apply_buff';
    }

    /**
     * Invalidate all cached friend zones for the given account (e.g. on session error 550 retry).
     */
    public static function invalidateFriendZonesForAccount(CacheRepository $cache, int $accountId): void
    {
        $current = (int) $cache->get("friend-zone:version:{$accountId}", 1);
        $cache->put("friend-zone:version:{$accountId}", $current + 1, 86400);
    }

    /**
     * Invalidate all cached home zones for the given account.
     */
    public static function invalidateHomeZoneForAccount(CacheRepository $cache, int $accountId): void
    {
        $current = (int) $cache->get("home-zone:version:{$accountId}", 1);
        $cache->put("home-zone:version:{$accountId}", $current + 1, 86400);
    }

    /**
     * @throws BuildingNotBuffableException
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
                $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                throw new FriendNotFoundException;
            }

            $version = (int) $this->cache->get("friend-zone:version:{$account->id}", 1);
            $friendZoneCacheKey = "friend-zone:exec:{$account->id}:{$targetPlayerIdInt}:v{$version}";
            $friendBuffCounterKey = "friend-zone:counter:{$account->id}:{$targetPlayerIdInt}";

            // Proactive keepalive: refresh friend zone every N actions to prevent server visitor session timeout
            $buffCounter = (int) $this->cache->get($friendBuffCounterKey, 0);
            if ($buffCounter >= self::FRIEND_ZONE_KEEPALIVE_THRESHOLD) {
                $this->cache->forget($friendZoneCacheKey);
                $this->cache->forget($friendBuffCounterKey);
            }

            /** @var array<string, mixed>|null $friendZoneData */
            $friendZoneData = $this->cache->get($friendZoneCacheKey);

            if (! is_array($friendZoneData)) {
                $friendZoneAmf = $this->amfService->getZone($account, $targetPlayerIdInt);
                $friendZoneData = $this->zoneParser->parse($friendZoneAmf);
                $err = (int) ($friendZoneData['errorCode'] ?? 0);

                if ($err !== 0) {
                    $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                    $errMsg = GameErrorResolver::getMessage($err);
                    throw new FriendZoneLoadException($err, $errMsg);
                }

                $this->cache->put($friendZoneCacheKey, $friendZoneData, 180);
                $this->cache->put($friendBuffCounterKey, 0, 180);
            }

            $friendName = (string) ($friend['username'] ?? $friend['nickname'] ?? $payload['target_player_name'] ?? 'Unknown');

            try {
                $this->buildingValidator->validate($friendZoneData, $grid, $friendName);
            } catch (BuildingNotBuffableException $e) {
                $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                throw $e;
            }

            $this->cache->increment($friendBuffCounterKey);

            $rawResult = $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount, $targetPlayerIdInt);
            $this->markBuildingBuffedInCache($friendZoneCacheKey, $grid, $acquiredBuff->buffName);

            return $rawResult;
        }

        // Target scope: self
        $version = (int) $this->cache->get("home-zone:version:{$account->id}", 1);
        $homeZoneCacheKey = "home-zone:exec:{$account->id}:v{$version}";

        /** @var array<string, mixed>|null $homeZoneData */
        $homeZoneData = $this->cache->get($homeZoneCacheKey);

        if (! is_array($homeZoneData)) {
            $homeZoneData = $this->resolveHomeZoneData($account);
            $this->cache->put($homeZoneCacheKey, $homeZoneData, 180);
        }

        $homeBuildings = $homeZoneData['buildings'] ?? [];

        if (! empty($homeBuildings) && is_array($homeBuildings)) {
            try {
                $this->buildingValidator->validate($homeBuildings, $grid, 'self');
            } catch (BuildingNotBuffableException $e) {
                $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                throw $e;
            }
        }

        $rawResult = $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount);
        $this->markBuildingBuffedInCache($homeZoneCacheKey, $grid, $acquiredBuff->buffName);

        return $rawResult;
    }

    /**
     * Resolve and sanitize home zone data for execution.
     * Refreshes from live game server if stale (> 60s) in production, and strips any residual synthetic buffs.
     *
     * @return array<string, mixed>
     */
    private function resolveHomeZoneData(Account $account): array
    {
        $isStale = $account->last_sync_at === null || $account->last_sync_at->diffInSeconds(now()) >= 60;

        if ($isStale && ! app()->environment('testing')) {
            try {
                $rawAmf = $this->amfService->getZone($account);
                $zoneData = $this->zoneParser->parse($rawAmf);
                $errorCode = (int) ($zoneData['errorCode'] ?? 0);

                if ($errorCode === 0 && ! empty($zoneData['buildings'])) {
                    if ($account->exists) {
                        $existingFriends = is_array($account->zone_data) && is_array($account->zone_data['friends'] ?? null)
                            ? $account->zone_data['friends']
                            : [];
                        if (! empty($existingFriends)) {
                            $zoneData['friends'] = $existingFriends;
                        }

                        $account->update([
                            'zone_data' => $zoneData,
                            'last_sync_at' => now(),
                            'status' => 'online',
                        ]);
                    }

                    return $zoneData;
                }
            } catch (\Throwable $e) {
                Log::warning("[ApplyBuffHandler] Failed to refresh live home zone for account #{$account->id}: {$e->getMessage()}");
            }
        }

        $rawZoneData = is_array($account->zone_data) ? $account->zone_data : [];
        [$sanitizedZone, $hadFakeBuffs] = $this->stripFakeBuffs($rawZoneData);

        if ($hadFakeBuffs && $account->exists) {
            $account->update([
                'zone_data' => $sanitizedZone,
            ]);
        }

        return $sanitizedZone;
    }

    /**
     * Strip any legacy fake buffs (remaining === 1) injected by previous versions of ApplyBuffHandler.
     *
     * @param  array<string, mixed>  $zoneData
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function stripFakeBuffs(array $zoneData): array
    {
        if (! isset($zoneData['buildings']) || ! is_array($zoneData['buildings'])) {
            return [$zoneData, false];
        }

        $hadFakeBuffs = false;
        foreach ($zoneData['buildings'] as $idx => $building) {
            if (! is_array($building) || empty($building['buffs']) || ! is_array($building['buffs'])) {
                continue;
            }

            $cleanBuffs = [];
            foreach ($building['buffs'] as $buff) {
                if (is_array($buff) && isset($buff['remaining']) && (int) $buff['remaining'] === 1 && ! isset($buff['uniqueId'])) {
                    $hadFakeBuffs = true;

                    continue;
                }
                $cleanBuffs[] = $buff;
            }

            $zoneData['buildings'][$idx]['buffs'] = $cleanBuffs;
        }

        return [$zoneData, $hadFakeBuffs];
    }

    /**
     * Mark building as buffed in cached zone data to prevent duplicate buff calls in same sequence.
     */
    private function markBuildingBuffedInCache(string $cacheKey, int $grid, string $buffName): void
    {
        /** @var array<string, mixed>|null $cachedZone */
        $cachedZone = $this->cache->get($cacheKey);
        if (! is_array($cachedZone) || ! isset($cachedZone['buildings']) || ! is_array($cachedZone['buildings'])) {
            return;
        }

        foreach ($cachedZone['buildings'] as $idx => $building) {
            if (! is_array($building)) {
                continue;
            }
            $bGrid = (int) ($building['buildingGrid'] ?? $building['grid'] ?? 0);
            if ($bGrid === $grid) {
                $buffs = is_array($building['buffs'] ?? null) ? $building['buffs'] : [];
                $buffs[] = [
                    'buffName_string' => $buffName,
                    'remaining' => 1,
                ];
                $cachedZone['buildings'][$idx]['buffs'] = $buffs;
                $this->cache->put($cacheKey, $cachedZone, 180);
                break;
            }
        }
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
