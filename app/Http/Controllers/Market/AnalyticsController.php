<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Enums\MarketItemKind;
use App\Http\Controllers\Controller;
use App\Services\Market\MarketAnalyticsService;
use App\Services\Market\Support\PeriodResolver;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Market analytics: server overview, or one trade pair when both item ids
 * are supplied.
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly MarketAnalyticsService $analytics,
        private readonly PeriodResolver $periods,
        private readonly MarketCacheService $cache,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
        ]);
        $kind = ($validated['kind'] ?? 'all') === 'all'
            ? null
            : MarketItemKind::from($validated['kind']);

        $serverId = $this->cache->resolveServerId($request->input('server_id'));
        $period = $this->periods->resolve($request->input('period', PeriodResolver::DEFAULT_PERIOD));

        $itemId = (string) $request->input('item_id');
        $targetItemId = (string) $request->input('target_item_id');

        if ($itemId === '' || $targetItemId === '') {
            return new JsonResponse($this->analytics->overview(
                $serverId,
                $period,
                (int) $request->input('page', 1),
                (int) $request->input('limit', config('market.offers_page_size')),
                $kind,
            ));
        }

        return new JsonResponse(
            $this->analytics->pair($serverId, $itemId, $targetItemId, $period)
        );
    }
}
