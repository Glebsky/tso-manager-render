<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketAnalyticsRequest;
use App\Services\Market\MarketAnalyticsService;
use App\Services\Market\Support\PeriodResolver;
use App\Services\MarketCacheService;
use Illuminate\Http\JsonResponse;

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

    public function __invoke(MarketAnalyticsRequest $request): JsonResponse
    {
        $kind = $request->kind();
        $serverId = $this->cache->resolveServerId($request->serverId());
        $period = $this->periods->resolve($request->periodKey());

        $itemId = $request->itemId();
        $targetItemId = $request->targetItemId();

        if ($itemId === '' || $targetItemId === '') {
            return new JsonResponse($this->analytics->overview(
                $serverId,
                $period,
                $request->page(),
                $request->limit(),
                $kind,
            ));
        }

        return new JsonResponse(
            $this->analytics->pair($serverId, $itemId, $targetItemId, $period)
        );
    }
}
