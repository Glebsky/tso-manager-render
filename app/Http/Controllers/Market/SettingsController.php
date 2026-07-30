<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\UpdateMarketSettingsRequest;
use App\Services\Market\MarketServerService;
use App\Services\Market\MarketSettingsService;
use App\Services\MarketCacheService;
use App\Support\Http\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Market synchronisation settings and the "sync now" action.
 */
final class SettingsController extends Controller
{
    public function __construct(
        private readonly MarketServerService $servers,
        private readonly MarketSettingsService $settings,
        private readonly MarketCacheService $cache,
        private readonly ApiResponder $responder,
    ) {}

    public function index(): JsonResponse
    {
        return $this->responder->data($this->servers->overview());
    }

    public function update(UpdateMarketSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->syncInterval(), $request->customIntervalMinutes());

        return $this->responder->success('Market settings updated.');
    }

    public function sync(Request $request): JsonResponse
    {
        $serverId = $this->cache->resolveServerId($request->input('server_id'));

        return $this->responder->data($this->servers->syncServerId($serverId));
    }
}
