<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Services\Market\MarketBulkService;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One request that returns the whole market dataset for a server.
 */
final class BulkController extends Controller
{
    public function __construct(
        private readonly MarketBulkService $bulk,
        private readonly MarketCacheService $cache,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $serverId = $this->cache->resolveServerId($request->input('server_id'));

        return new JsonResponse($this->bulk->payload($serverId));
    }
}
