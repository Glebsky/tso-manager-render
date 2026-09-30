<?php

declare(strict_types=1);

namespace App\Services\Game\Zone;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Version-bump invalidation: cached zones are keyed by a per-account version
 * (`home-zone:exec:{id}:v{n}`, friend zones likewise), so bumping the version
 * orphans every existing entry at once without enumerating keys.
 */
final readonly class VersionedZoneCacheInvalidator implements ZoneCacheInvalidator
{
    private const int VERSION_TTL_SECONDS = 86400;

    public function __construct(private CacheRepository $cache) {}

    public function invalidateHomeZone(int $accountId): void
    {
        $this->bump(self::homeZoneVersionKey($accountId));
    }

    public function invalidateFriendZones(int $accountId): void
    {
        $this->bump(self::friendZoneVersionKey($accountId));
    }

    public function homeZoneVersion(int $accountId): int
    {
        return (int) $this->cache->get(self::homeZoneVersionKey($accountId), 1);
    }

    public function friendZoneVersion(int $accountId): int
    {
        return (int) $this->cache->get(self::friendZoneVersionKey($accountId), 1);
    }

    public static function homeZoneVersionKey(int $accountId): string
    {
        return "home-zone:version:{$accountId}";
    }

    public static function friendZoneVersionKey(int $accountId): string
    {
        return "friend-zone:version:{$accountId}";
    }

    private function bump(string $key): void
    {
        $this->cache->put($key, (int) $this->cache->get($key, 1) + 1, self::VERSION_TTL_SECONDS);
    }
}
