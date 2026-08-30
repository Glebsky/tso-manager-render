<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketSyncLogResource;
use App\Models\MarketSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paginated market synchronisation log.
 */
final class SyncLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $serverId = $request->input('server_id');

        $paginator = MarketSyncLog::query()
            ->when(! empty($serverId), fn ($query) => $query->where('server_id', $serverId))
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('limit', 10));

        return new JsonResponse([
            'data' => MarketSyncLogResource::collection(collect($paginator->items()))->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }
}
