<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\StoreMarketServerRequest;
use App\Http\Requests\Market\UpdateMarketServerRequest;
use App\Models\MarketServerConnection;
use App\Services\Market\MarketServerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Market server connections (CRUD, verification, manual sync).
 */
final class ServerController extends Controller
{
    public function __construct(
        private readonly MarketServerService $servers,
    ) {}

    public function index(): JsonResponse
    {
        return new JsonResponse($this->servers->overview());
    }

    public function store(StoreMarketServerRequest $request): JsonResponse
    {
        $server = $this->servers->createForAccount($request->accountId());

        return new JsonResponse([
            'success' => true,
            'server' => $server,
            'message' => $this->servers->createdMessage($server),
        ], 201);
    }

    public function update(UpdateMarketServerRequest $request, MarketServerConnection $server): JsonResponse
    {
        $updated = $this->servers->update($server, $request->validated());

        return new JsonResponse([
            'success' => true,
            'server' => $updated,
            'message' => __('ui.market.api.server_updated'),
        ]);
    }

    public function destroy(MarketServerConnection $server): Response
    {
        $this->servers->delete($server);

        return response()->noContent();
    }

    public function verify(MarketServerConnection $server): JsonResponse
    {
        $result = $this->servers->verify($server);

        return new JsonResponse($result['payload'], $result['status']);
    }

    public function sync(MarketServerConnection $server): JsonResponse
    {
        return new JsonResponse($this->servers->syncNow($server));
    }
}
