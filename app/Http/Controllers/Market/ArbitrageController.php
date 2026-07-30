<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Services\Market\Contracts\ArbitrageFinder;
use App\Services\MarketCacheService;
use App\Support\Http\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profitable barter loops among the currently active offers.
 *
 * The controller depends on the {@see ArbitrageFinder} abstraction, so the
 * detection algorithm can be replaced without touching this class (DIP/OCP).
 */
final class ArbitrageController extends Controller
{
    public function __construct(
        private readonly ArbitrageFinder $arbitrage,
        private readonly MarketCacheService $cache,
        private readonly ApiResponder $responder,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $serverId = $this->cache->resolveServerId($request->input('server_id'));

        $result = $this->cache->remember(
            $serverId,
            'arbitrage',
            [],
            (int) config('market.cache_ttl.arbitrage'),
            fn (): array => $this->arbitrage->find($serverId)
        );

        return $this->responder->data($result);
    }
}
