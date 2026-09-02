<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketPopularRequest;
use App\Http\Resources\PopularItemResource;
use App\Services\Market\PopularItemService;
use App\Services\Market\Support\PeriodResolver;
use Illuminate\Http\JsonResponse;

/**
 * Controller for retrieving popular (most traded) market items.
 */
final class PopularController extends Controller
{
    public function __construct(
        private readonly PopularItemService $popular,
        private readonly PeriodResolver $periodResolver,
    ) {}

    public function __invoke(MarketPopularRequest $request): JsonResponse
    {
        $period = $this->periodResolver->resolve($request->periodKey());
        $items = $this->popular->popular($request->serverId(), $period, $request->kind());

        return new JsonResponse(PopularItemResource::collection($items)->resolve());
    }
}
