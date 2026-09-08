<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketSyncLogRequest;
use App\Http\Resources\MarketSyncLogResource;
use App\Models\MarketSyncLog;
use Illuminate\Http\JsonResponse;

/**
 * Paginated market synchronisation log.
 */
final class SyncLogController extends Controller
{
    public function __invoke(MarketSyncLogRequest $request): JsonResponse
    {
        $serverId = $request->serverId();

        $paginator = MarketSyncLog::query()
            ->when($serverId !== null, fn ($query) => $query->where('server_id', $serverId))
            ->orderByDesc('created_at')
            ->paginate($request->limit());

        return new JsonResponse([
            'data' => MarketSyncLogResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }
}
