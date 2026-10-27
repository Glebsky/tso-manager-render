<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Exceptions\BuffUnavailableException;
use App\Exceptions\BuildingNotBuffableException;
use App\Exceptions\FriendBuildingNotFoundException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\FriendZoneLoadException;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Game\Zone\HomeZoneRepository;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\Tasks\Execution\BuffAcquisitionResult;
use App\Services\Tasks\Execution\BuffAvailabilityManager;
use App\Services\Tasks\Execution\BuildingBuffEligibilityValidator;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

final readonly class ApplyBuffHandler implements TaskActionHandlerInterface
{
    private const int FRIEND_ZONE_KEEPALIVE_THRESHOLD = 15;

    private const int FRIEND_ZONE_CACHE_TTL_SECONDS = 180;

    /** A cached home zone older than this is re-read once before a building is rejected. */
    private const int HOME_ZONE_RECHECK_AFTER_SECONDS = 60;

    /** Session errors are recovered by the action's GameSessionGuard, never swallowed here. */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    /** Only these fields are read by BuildingBuffEligibilityValidator. */
    private const array VALIDATION_BUILDING_KEYS = ['buildingName_string', 'buildingName', 'buildingGrid', 'grid', 'upgradeIsInProgress', 'buildingMode', 'isProductionActive'];

    private const array VALIDATION_BUFF_KEYS = ['buffName_string', 'name_string', 'buff_name'];

    public function __construct(
        private TsoAmfService $amfService,
        private ZoneParserService $zoneParser,
        private BuffAvailabilityManager $buffAvailabilityManager,
        private BuildingBuffEligibilityValidator $buildingValidator,
        private CacheRepository $cache,
        private VersionedZoneCacheInvalidator $zoneCache,
        private HomeZoneRepository $homeZones,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'apply_buff';
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

            $version = $this->zoneCache->friendZoneVersion($account->id);
            $friendZoneCacheKey = "friend-zone:exec:{$account->id}:{$targetPlayerIdInt}:v{$version}";
            $friendMarksKey = "friend-zone:buffed:{$account->id}:{$targetPlayerIdInt}:v{$version}";
            $friendBuffCounterKey = "friend-zone:counter:{$account->id}:{$targetPlayerIdInt}";

            // Proactive keepalive: refresh friend zone every N actions to prevent server visitor session timeout
            $buffCounter = (int) $this->cache->get($friendBuffCounterKey, 0);
            if ($buffCounter >= self::FRIEND_ZONE_KEEPALIVE_THRESHOLD) {
                $this->cache->forget($friendZoneCacheKey);
                $this->cache->forget($friendMarksKey);
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

                // Only what the eligibility check reads is kept in remote Redis.
                $friendZoneData = $this->compactForValidation($friendZoneData);
                $this->cache->put($friendZoneCacheKey, $friendZoneData, self::FRIEND_ZONE_CACHE_TTL_SECONDS);
                $this->cache->put($friendBuffCounterKey, 0, self::FRIEND_ZONE_CACHE_TTL_SECONDS);
            }

            $friendName = (string) ($friend['username'] ?? $friend['nickname'] ?? $payload['target_player_name'] ?? 'Unknown');

            try {
                $this->buildingValidator->validate($this->withBuffMarks($friendZoneData, $friendMarksKey), $grid, $friendName);
            } catch (BuildingNotBuffableException $e) {
                $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                throw $e;
            }

            $this->cache->increment($friendBuffCounterKey);

            $rawResult = $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount, $targetPlayerIdInt);
            $this->markBuildingBuffed($friendMarksKey, $grid, $acquiredBuff->buffName, self::FRIEND_ZONE_CACHE_TTL_SECONDS);

            return $rawResult;
        }

        // Target scope: self
        $version = $this->zoneCache->homeZoneVersion($account->id);
        $homeMarksKey = "home-zone:buffed:{$account->id}:v{$version}";

        $homeZoneData = $this->resolveHomeZoneData($account, $payload, $acquiredBuff);
        $homeBuildings = $homeZoneData['buildings'] ?? [];

        if (! empty($homeBuildings) && is_array($homeBuildings)) {
            try {
                $this->buildingValidator->validate($this->withBuffMarks($homeZoneData, $homeMarksKey), $grid, 'self');
            } catch (BuildingNotBuffableException $e) {
                $this->revalidateWithLiveHomeZone($account, $payload, $acquiredBuff, $homeMarksKey, $grid, $e);
            }
        }

        $rawResult = $this->amfService->applyBuff($account, $grid, $uniqueId1, $uniqueId2, $amount);
        $this->markBuildingBuffed($homeMarksKey, $grid, $acquiredBuff->buffName, max(180, HomeZoneRepository::ttlSeconds()));

        return $rawResult;
    }

    /**
     * Home zone for the eligibility check: the persisted zone while it is fresh
     * (consecutive buffs no longer re-read ~1 MB each), otherwise one live read.
     * Session errors propagate to the action's guard; other failures fall back
     * to the stored zone as before. Residual synthetic buffs are stripped.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function resolveHomeZoneData(Account $account, array $payload, BuffAcquisitionResult $acquiredBuff): array
    {
        try {
            $zoneData = $this->homeZones->get($account);
        } catch (SessionBusyException|SessionExpiredException $e) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw $e;
        } catch (GameServerErrorException $e) {
            if (in_array((int) $e->getCode(), self::SESSION_ERROR_CODES, true)) {
                $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
                throw $e;
            }
            Log::warning("[ApplyBuffHandler] Failed to refresh live home zone for account #{$account->id}: {$e->getMessage()}");
            $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
        } catch (\Throwable $e) {
            Log::warning("[ApplyBuffHandler] Failed to refresh live home zone for account #{$account->id}: {$e->getMessage()}");
            $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
        }

        [$sanitizedZone, $hadFakeBuffs] = $this->stripFakeBuffs($zoneData);

        if ($hadFakeBuffs && $account->exists) {
            $account->update([
                'zone_data' => $sanitizedZone,
            ]);
        }

        return $sanitizedZone;
    }

    /**
     * A building rejected on a cached zone may have changed since (buff expired,
     * production restarted): re-read the zone once before skipping the step.
     * A rejection caused by our own buff in this window is final.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws BuildingNotBuffableException
     * @throws \Exception
     */
    private function revalidateWithLiveHomeZone(Account $account, array $payload, BuffAcquisitionResult $acquiredBuff, string $marksKey, int $grid, BuildingNotBuffableException $rejection): void
    {
        $marks = $this->cache->get($marksKey);
        $age = $this->homeZones->ageSeconds($account);
        if ((is_array($marks) && isset($marks[$grid])) || ($age !== null && $age < self::HOME_ZONE_RECHECK_AFTER_SECONDS)) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw $rejection;
        }

        try {
            [$liveZone] = $this->stripFakeBuffs($this->homeZones->refresh($account));
        } catch (SessionBusyException|SessionExpiredException $e) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw $e;
        } catch (GameServerErrorException $e) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw in_array((int) $e->getCode(), self::SESSION_ERROR_CODES, true) ? $e : $rejection;
        } catch (\Throwable) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw $rejection;
        }

        try {
            $this->buildingValidator->validate($this->withBuffMarks($liveZone, $marksKey), $grid, 'self');
        } catch (BuildingNotBuffableException $e) {
            $this->buffAvailabilityManager->release($account, $payload, $acquiredBuff);
            throw $e;
        }
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
     * @param  array<string, mixed>  $zone
     * @return array{buildings: list<array<string, mixed>>}
     */
    private function compactForValidation(array $zone): array
    {
        $buildingKeys = array_flip(self::VALIDATION_BUILDING_KEYS);
        $buffKeys = array_flip(self::VALIDATION_BUFF_KEYS);
        $buildings = [];

        foreach ((array) ($zone['buildings'] ?? []) as $building) {
            if (! is_array($building)) {
                continue;
            }
            $compact = array_intersect_key($building, $buildingKeys);
            if (is_array($building['buffs'] ?? null) && $building['buffs'] !== []) {
                $compact['buffs'] = array_values(array_map(
                    static fn (mixed $buff): mixed => is_array($buff) ? array_intersect_key($buff, $buffKeys) : $buff,
                    $building['buffs'],
                ));
            }
            $buildings[] = $compact;
        }

        return ['buildings' => $buildings];
    }

    /**
     * Overlay buffs applied by us in this window (kept as a tiny grid => name
     * map instead of rewriting the whole cached zone after every buff).
     *
     * @param  array<string, mixed>  $zone
     * @return array<string, mixed>
     */
    private function withBuffMarks(array $zone, string $marksKey): array
    {
        $marks = $this->cache->get($marksKey);
        if (! is_array($marks) || $marks === [] || ! is_array($zone['buildings'] ?? null)) {
            return $zone;
        }

        foreach ($zone['buildings'] as $idx => $building) {
            if (! is_array($building)) {
                continue;
            }
            $bGrid = (int) ($building['buildingGrid'] ?? $building['grid'] ?? 0);
            if (! isset($marks[$bGrid])) {
                continue;
            }
            $buffs = is_array($building['buffs'] ?? null) ? $building['buffs'] : [];
            $buffs[] = [
                'buffName_string' => (string) $marks[$bGrid],
                'remaining' => 1,
            ];
            $zone['buildings'][$idx]['buffs'] = $buffs;
        }

        return $zone;
    }

    /**
     * Mark building as buffed to prevent duplicate buff calls in the same window.
     */
    private function markBuildingBuffed(string $marksKey, int $grid, string $buffName, int $ttlSeconds): void
    {
        $marks = $this->cache->get($marksKey);
        $marks = is_array($marks) ? $marks : [];
        $marks[$grid] = $buffName;
        $this->cache->put($marksKey, $marks, $ttlSeconds);
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
