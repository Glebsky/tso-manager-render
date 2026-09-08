<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketCatalogGoodsRequest;
use App\Http\Requests\Market\MarketCatalogTargetsRequest;
use App\Services\Market\MarketCatalogService;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;

/**
 * Traded goods and their trade targets.
 */
final class CatalogController extends Controller
{
    public function __construct(
        private readonly MarketCatalogService $catalog,
        private readonly MarketCacheService $cache,
    ) {}

    public function goods(MarketCatalogGoodsRequest $request): JsonResponse
    {
        $kind = $request->kind();
        $serverId = $this->cache->resolveServerId($request->serverId());

        return new JsonResponse($this->catalog->goods($serverId, $kind));
    }

    public function targets(MarketCatalogTargetsRequest $request): JsonResponse
    {
        $itemId = $request->itemId();

        if ($itemId === '') {
            return new JsonResponse([]);
        }

        $kind = $request->kind();
        $serverId = $this->cache->resolveServerId($request->serverId());

        return new JsonResponse($this->catalog->targets($serverId, $itemId, $kind));
    }
}
