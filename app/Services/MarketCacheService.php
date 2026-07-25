<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class MarketCacheService
{
    /**
     * Get current data version for a given server.
     */
    public function dataVersion(string $serverId): int
    {
        return (int) Cache::get($this->versionKey($serverId), 1);
    }

    /**
     * Increment data version for a server after successful sync or update.
     */
    public function bumpDataVersion(string $serverId): int
    {
        $key = $this->versionKey($serverId);
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }

    /**
     * Remember cached data per server and endpoint.
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
     * Generate ETag string based on server, data version, locale and params.
     */
    public function generateETag(string $serverId, string $endpoint, array $params): string
    {
        $version = $this->dataVersion($serverId);
        $locale = (string) app()->getLocale();
        $paramsHash = md5((string) json_encode($this->canonicalizeParams($params)));

        return sprintf('"%s-v%d-%s-%s-%s"', $serverId, $version, $locale, $endpoint, substr($paramsHash, 0, 8));
    }

    private function versionKey(string $serverId): string
    {
        return "market:data_version:{$serverId}";
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
