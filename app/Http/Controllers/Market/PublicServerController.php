<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicServerResource;
use App\Services\Market\MarketServerService;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;

/**
 * Servers exposed to the public portal.
 */
final class PublicServerController extends Controller
{
    public function __construct(
        private readonly MarketServerService $servers,
        private readonly MarketCacheService $cache,
    ) {}

    public function __invoke(): JsonResponse
    {
        $data = $this->cache->remember(
            MarketCacheService::GLOBAL_SERVER,
            'public_servers',
            [],
            (int) config('market.cache_ttl.public_servers'),
            fn (): array => PublicServerResource::collection($this->servers->publicServers())->resolve()
        );

        return new JsonResponse($data);
    }
}
