<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Account;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Semantic validation of an `apply_buff` payload.
 *
 * Answers one question only: can this account really apply this buff to this
 * target right now? Rule-shape validation stays in the form request, HTTP
 * concerns stay in the controller.
 *
 * Returns the collected errors instead of throwing, so the caller decides how
 * to surface them (form request, console command, queued job).
 */
final class BuffPayloadValidator
{
    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    public function validate(Account $account, array $payload, string $prefix = 'payload.'): array
    {
        $targetScope = $payload['target_scope'] ?? 'self';
        $amount = $payload['amount'] ?? 1;
        $zoneData = $this->zoneData($account);

        $buffs = $zoneData['availableBuffs'] ?? $zoneData['buffs'] ?? [];
        $buffFound = false;

        foreach ($buffs as $buff) {
            $u1 = $buff['uniqueId1'] ?? $buff['uniqueID1'] ?? $buff['uniqueID']['uniqueID1'] ?? $buff['uniqueID']['uniqueId1'] ?? $buff['uniqueId']['uniqueId1'] ?? null;
            $u2 = $buff['uniqueId2'] ?? $buff['uniqueID2'] ?? $buff['uniqueID']['uniqueID2'] ?? $buff['uniqueID']['uniqueId2'] ?? $buff['uniqueId']['uniqueId2'] ?? null;

            if ($u1 == ($payload['unique_id1'] ?? null) && $u2 == ($payload['unique_id2'] ?? null)) {
                $buffFound = true;
                $availableAmount = $buff['amount'] ?? 0;

                if ($availableAmount < $amount) {
                    return [$prefix.'amount' => [__('tasks.error.insufficient_buffs', ['available' => $availableAmount, 'required' => $amount])]];
                }

                break;
            }
        }

        if (! $buffFound) {
            return [$prefix.'unique_id1' => [__('tasks.error.buff_not_found')]];
        }

        if ($targetScope !== 'friend') {
            return [];
        }

        return $this->validateFriendTarget($account, $payload, $prefix, $zoneData);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $zoneData
     * @return array<string, list<string>>
     */
    private function validateFriendTarget(Account $account, array $payload, string $prefix, array $zoneData): array
    {
        $friendId = (int) ($payload['target_player_id'] ?? 0);

        if ($friendId < 1) {
            return [$prefix.'target_player_id' => [__('tasks.error.invalid_friend_id')]];
        }

        if (! $this->isKnownFriend($zoneData, $friendId)) {
            return [$prefix.'target_player_id' => [__('tasks.error.friend_not_in_list')]];
        }

        $cachedZone = $this->cache->get("friend-zone:{$account->id}:{$friendId}");

        if (! $cachedZone) {
            return [$prefix.'target_player_id' => [__('tasks.error.friend_zone_not_cached')]];
        }

        $friendZoneData = json_decode((string) $cachedZone, true) ?: [];

        foreach ($friendZoneData['buildings'] ?? [] as $building) {
            if (($building['buildingGrid'] ?? null) == ($payload['grid'] ?? null)) {
                return [];
            }
        }

        return [$prefix.'grid' => [__('tasks.error.friend_building_not_found_grid', ['grid' => $payload['grid'] ?? null])]];
    }

    /**
     * @param  array<string, mixed>  $zoneData
     */
    private function isKnownFriend(array $zoneData, int $friendId): bool
    {
        foreach ($zoneData['friends'] ?? [] as $friend) {
            if (isset($friend['id']) && (int) $friend['id'] === $friendId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneData(Account $account): array
    {
        return is_array($account->zone_data) ? $account->zone_data : [];
    }
}
