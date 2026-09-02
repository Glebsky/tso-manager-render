<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Enums\MarketItemKind;
use App\Http\Controllers\Controller;
use App\Services\Market\MarketCatalogService;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Traded goods and their trade targets.
 */
final class CatalogController extends Controller
{
    public function __construct(
        private readonly MarketCatalogService $catalog,
        private readonly MarketCacheService $cache,
    ) {}

    public function goods(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
        ]);
        $kind = ($validated['kind'] ?? 'all') === 'all'
            ? null
            : MarketItemKind::from($validated['kind']);

        $serverId = $this->cache->resolveServerId($request->input('server_id'));

        return new JsonResponse($this->catalog->goods($serverId, $kind));
    }

    public function targets(Request $request): JsonResponse
    {
        $itemId = (string) $request->input('item_id');

        if ($itemId === '') {
            return new JsonResponse([]);
        }

        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
        ]);
        $kind = ($validated['kind'] ?? 'all') === 'all'
            ? null
            : MarketItemKind::from($validated['kind']);

        $serverId = $this->cache->resolveServerId($request->input('server_id'));

        return new JsonResponse($this->catalog->targets($serverId, $itemId, $kind));
    }
}
