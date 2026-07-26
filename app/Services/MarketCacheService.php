<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarketServerConnection;
use App\Models\Setting;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketCacheService
{
    /**
     * Pseudo-server id used for cross-server (aggregate) cached data.
     */
    public const GLOBAL_SERVER = 'global';

    /**
     * Micro-TTL (seconds) for the cached copy of the DB-stored data version.
     * Keeps per-request version lookups cheap without long staleness windows.
     */
    private const VERSION_MICRO_TTL_SECONDS = 5;

    /**
     * Time bucket size (seconds) mixed into ETags. Responses that depend on
     * the current time (now()-based filters, time_left, period windows) must
     * never be served as "304 Not Modified" longer than one bucket, even if
     * the data version has not changed between syncs.
     */
    private const ETAG_TIME_BUCKET_SECONDS = 60;

    /**
     * Resolve target server_id from string input or fallback to first available.
     */
    public function resolveServerId(?string $serverId = null): string
    {
        if (! empty($serverId)) {
            return $serverId;
        }

        return (string) (MarketServerConnection::whereNotNull('account_id')->value('server_id')
            ?? MarketServerConnection::value('server_id')
            ?? 'ru');
    }

    /**
     * Get the current data version for a given server.
     *
     * Source of truth is the `data_version` column on
     * `market_server_connections`: it survives cache clears and deploys and
     * is shared by every process (web, queue worker, scheduler). A micro-TTL
     * cached copy keeps the per-request overhead negligible.
     *
     * The GLOBAL_SERVER version is derived as the sum of all server versions,
     * so bumping (or adding/removing) any server automatically invalidates
     * global cache keys.
     */
    public function dataVersion(string $serverId): int
    {
        return (int) Cache::remember(
            $this->versionMicroCacheKey($serverId),
            self::VERSION_MICRO_TTL_SECONDS,
            function () use ($serverId): int {
                if ($serverId === self::GLOBAL_SERVER) {
                    return (int) MarketServerConnection::sum('data_version');
                }

                $version = MarketServerConnection::where('server_id', $serverId)->value('data_version');
                if ($version !== null) {
                    return (int) $version;
                }

                // Server ids without a connection row (e.g. region fallbacks)
                // keep a durable counter in the settings table.
                return (int) Setting::get($this->fallbackVersionSettingKey($serverId), 0);
            }
        );
    }

    /**
     * Increment the data version of a server after a successful sync or a
     * data mutation. Old cache entries become unreachable (the version is
     * part of every cache key) and simply expire by their TTL, so no key
     * registry bookkeeping is required.
     *
     * IMPORTANT: call this only after the corresponding DB transaction has
     * committed, otherwise readers may cache pre-commit data under the new
     * version.
     */
    public function bumpDataVersion(string $serverId): int
    {
        if ($serverId !== self::GLOBAL_SERVER) {
            $updated = MarketServerConnection::where('server_id', $serverId)->increment('data_version');

            if ($updated === 0) {
                $key = $this->fallbackVersionSettingKey($serverId);
                Setting::set($key, (string) ((int) Setting::get($key, 0) + 1));
            }
        }

        // Drop the micro-cached copies so the new version is visible to the
        // very next request on this machine.
        Cache::forget($this->versionMicroCacheKey($serverId));
        Cache::forget($this->versionMicroCacheKey(self::GLOBAL_SERVER));

        return $this->dataVersion($serverId);
    }

    /**
     * Remember cached data per server and endpoint (L3 application cache).
     */
    public function remember(string $serverId, string $endpoint, array $params, int $ttlSeconds, Closure $callback): mixed
    {
        $version = $this->dataVersion($serverId);
        $locale = (string) app()->getLocale();
        $paramsHash = md5((string) json_encode($this->canonicalizeParams($params)));
        $cacheKey = "market:v{$version}:{$serverId}:{$locale}:{$endpoint}:{$paramsHash}";

        return Cache::remember($cacheKey, $ttlSeconds, $callback);
    }

    /**
     * Generate an ETag based on server, data version, locale, params and a
     * coarse time bucket. The bucket guarantees that time-dependent
     * responses are revalidated at least once per bucket even between syncs.
     */
    public function generateETag(string $serverId, string $endpoint, array $params): string
    {
        $version = $this->dataVersion($serverId);
        $locale = (string) app()->getLocale();
        $paramsHash = md5((string) json_encode($this->canonicalizeParams($params)));
        $timeBucket = intdiv(Carbon::now()->getTimestamp(), self::ETAG_TIME_BUCKET_SECONDS);

        return sprintf('"%s-v%d-%s-%s-%s-t%d"', $serverId, $version, $locale, $endpoint, substr($paramsHash, 0, 8), $timeBucket);
    }

    private function versionMicroCacheKey(string $serverId): string
    {
        return "market:data_version:{$serverId}";
    }

    private function fallbackVersionSettingKey(string $serverId): string
    {
        return "market_data_version:{$serverId}";
    }

    /**
     * Recursively sort parameters by key for canonical cache keys.
     */
    private function canonicalizeParams(array $params): array
    {
        ksort($params);
        foreach ($params as $key => $val) {
            if (is_array($val)) {
                $params[$key] = $this->canonicalizeParams($val);
            }
        }

        return $params;
    }
}
