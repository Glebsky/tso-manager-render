<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketVersionRequest;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;

/**
 * Exposes the current data version so clients can invalidate their local
 * (localStorage / SWR) caches. The response must never be cached itself.
 */
final class VersionController extends Controller
{
    public function __construct(
        private readonly MarketCacheService $cache,
    ) {}

    public function __invoke(MarketVersionRequest $request): JsonResponse
    {
        $serverId = $this->cache->resolveServerId($request->serverId());
        $dataVersion = $this->cache->dataVersion($serverId);

        $response = new JsonResponse([
            'server_id' => $serverId,
            'data_version' => $dataVersion,
        ]);

        $response->headers->set('X-Data-Version', (string) $dataVersion);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
