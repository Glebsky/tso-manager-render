<?php

declare(strict_types=1);

namespace App\Services\Game\Zone;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\GameErrorResolver;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * The player's home zone, read from the game at most once per freshness window.
 *
 * The persisted `accounts.zone_data` (+ `last_sync_at`) is the cache: it is
 * fresh while younger than `game.home_zone_cache_ttl_seconds` and no mutation
 * invalidated the home zone since it was written. Nothing zone-sized is kept
 * in remote Redis (each write there is paid outbound traffic); only the tiny
 * invalidation marker of {@see VersionedZoneCacheInvalidator} lives there.
 * Account sync is the manual refresh: it persists a fresh zone the same way.
 * Live reads go through {@see GameSessionGuard}, so they recover a superseded
 * or expired session exactly like task actions do.
 */
final readonly class HomeZoneRepository
{
    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $parser,
        private GameSessionGuard $guard,
        private VersionedZoneCacheInvalidator $invalidator,
    ) {}

    public static function ttlSeconds(): int
    {
        return max(0, (int) config('game.home_zone_cache_ttl_seconds', 600));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function get(
        Account $account,
        bool $forceRefresh = false,
        ?int $maxAgeSeconds = null,
        bool $allowStaleFallback = true,
        int $maxStaleAgeSeconds = 3600
    ): array {
        if (! $forceRefresh) {
            $cached = $this->fresh($account, $maxAgeSeconds);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            return $this->refresh($account);
        } catch (GameServerErrorException $e) {
            if ($allowStaleFallback && (int) $e->getCode() === 1001) {
                $stale = $this->stale($account, $maxStaleAgeSeconds);
                if ($stale !== null) {
                    Log::warning(sprintf(
                        '[HomeZoneRepository] Account #%d: live zone read returned 1001 (initializing). Using stale zone fallback (age: %ds).',
                        $account->id,
                        (int) ($this->ageSeconds($account) ?? 0)
                    ));

                    return $stale;
                }
            }

            throw $e;
        }
    }

    /**
     * The persisted zone when available, even if stale (up to maxStaleAgeSeconds).
     *
     * @return array<string, mixed>|null
     */
    public function stale(Account $account, int $maxStaleAgeSeconds = 3600): ?array
    {
        $zone = $account->zone_data;
        if (! is_array($zone) || ! is_array($zone['buildings'] ?? null) || $zone['buildings'] === []) {
            return null;
        }

        if (! $account->last_sync_at instanceof CarbonInterface) {
            return null;
        }

        $age = abs($account->last_sync_at->diffInSeconds(now()));
        if ($age > $maxStaleAgeSeconds) {
            return null;
        }

        return $zone;
    }

    /**
     * The persisted zone when it is still trustworthy, otherwise null.
     *
     * @return array<string, mixed>|null
     */
    public function fresh(Account $account, ?int $maxAgeSeconds = null): ?array
    {
        $zone = $account->zone_data;
        if (! is_array($zone) || ! is_array($zone['buildings'] ?? null) || $zone['buildings'] === []) {
            return null;
        }

        $age = $this->ageSeconds($account);
        if ($age === null || $age >= ($maxAgeSeconds ?? self::ttlSeconds())) {
            return null;
        }

        return $zone;
    }

    /** Seconds since the persisted zone was read from the game; null when unknown or invalidated. */
    public function ageSeconds(Account $account): ?float
    {
        $syncedAt = $account->last_sync_at;
        if (! $syncedAt instanceof CarbonInterface) {
            return null;
        }

        $invalidatedAt = $this->invalidator->homeZoneInvalidatedAt((int) $account->id);
        // last_sync_at keeps whole (truncated) seconds: a zone written within the
        // invalidation's second compares as "not after it" and is not trusted.
        if ($invalidatedAt !== null && $syncedAt->getPreciseTimestamp(6) / 1_000_000 <= $invalidatedAt) {
            return null;
        }

        return abs($syncedAt->diffInSeconds(now()));
    }

    /**
     * One guarded live CMD_ZONE_LOGIN read, persisted once.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function refresh(Account $account): array
    {
        $zone = $this->guard->run($account, 'Home zone read', function () use ($account): array {
            $zone = $this->parser->parse($this->amf->getZone($account));
            $errorCode = (int) ($zone['errorCode'] ?? 0);
            if ($errorCode !== 0) {
                throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
            }

            return $zone;
        });

        return $this->persist($account, $zone);
    }

    /**
     * @param  array<string, mixed>  $zone
     * @return array<string, mixed>
     */
    public function persist(Account $account, array $zone): array
    {
        $existingFriends = is_array($account->zone_data) && is_array($account->zone_data['friends'] ?? null)
            ? $account->zone_data['friends']
            : [];
        if ($existingFriends !== []) {
            $zone['friends'] = $existingFriends;
        }

        if ($account->exists) {
            $account->update([
                'zone_data' => json_encode($zone, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'last_sync_at' => now(),
                'status' => 'online',
            ]);
        }

        return $zone;
    }
}
