<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\StoreMarketServerRequest;
use App\Http\Requests\Market\UpdateMarketServerRequest;
use App\Models\MarketServerConnection;
use App\Services\Market\MarketServerService;
use App\Support\Http\ApiResponder;
use Illuminate\Http\JsonResponse;

/**
 * Market server connections (CRUD, verification, manual sync).
 *
 * The controller only translates HTTP to a use case and back; all rules live
 * in {@see MarketServerService}.
 */
final class ServerController extends Controller
{
    public function __construct(
        private readonly MarketServerService $servers,
        private readonly ApiResponder $responder,
    ) {}

    public function index(): JsonResponse
    {
        return $this->responder->data($this->servers->overview());
    }

    public function store(StoreMarketServerRequest $request): JsonResponse
    {
        $server = $this->servers->createForAccount($request->accountId());

        return $this->responder->success(
            $this->servers->createdMessage($server),
            ['server' => $server]
        );
    }

    public function update(UpdateMarketServerRequest $request, MarketServerConnection $server): JsonResponse
    {
        $updated = $this->servers->update($server, $request->validated());

        return $this->responder->success(
            __('ui.market.api.server_updated'),
            ['server' => $updated]
        );
    }

    public function destroy(MarketServerConnection $server): JsonResponse
    {
        $this->servers->delete($server);

        return $this->responder->success(__('ui.market.api.server_deleted'));
    }

    public function verify(MarketServerConnection $server): JsonResponse
    {
        $result = $this->servers->verify($server);

        return $this->responder->data($result['payload'], $result['status']);
    }

    public function sync(MarketServerConnection $server): JsonResponse
    {
        return $this->responder->data($this->servers->syncNow($server));
    }
}
