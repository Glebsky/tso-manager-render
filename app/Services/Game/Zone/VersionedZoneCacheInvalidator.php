<?php

declare(strict_types=1);

namespace App\Services\Game\Zone;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Version-bump invalidation: cached zone entries are keyed by a per-account
 * version (`home-zone:buffed:{id}:v{n}`, `friend-zone:exec:{id}:{fid}:v{n}`),
 * so bumping the version orphans every existing entry at once without
 * enumerating keys. The persisted home zone is distrusted via the
 * invalidated-at marker instead (see HomeZoneRepository).
 */
final readonly class VersionedZoneCacheInvalidator implements ZoneCacheInvalidator
{
    private const int VERSION_TTL_SECONDS = 86400;

    public function __construct(private CacheRepository $cache) {}

    public function invalidateHomeZone(int $accountId): void
    {
        $this->bump(self::homeZoneVersionKey($accountId));
        // Lets HomeZoneRepository distrust a persisted zone written before now.
        $this->cache->put(self::homeZoneInvalidatedAtKey($accountId), microtime(true), self::VERSION_TTL_SECONDS);
    }

    /** Unix time (with fraction) of the last home zone invalidation, if any. */
    public function homeZoneInvalidatedAt(int $accountId): ?float
    {
        $value = $this->cache->get(self::homeZoneInvalidatedAtKey($accountId));

        return is_numeric($value) ? (float) $value : null;
    }

    public static function homeZoneInvalidatedAtKey(int $accountId): string
    {
        return "home-zone:invalidated-at:{$accountId}";
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
