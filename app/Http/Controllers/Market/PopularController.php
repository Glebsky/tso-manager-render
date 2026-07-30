<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketPopularRequest;
use App\Http\Resources\PopularItemResource;
use App\Services\Market\PopularItemService;
use App\Services\Market\Support\PeriodResolver;
use App\Support\Http\ApiResponder;
use Illuminate\Http\JsonResponse;

/**
 * Controller for retrieving popular (most traded) market items.
 */
final class PopularController extends Controller
{
    public function __construct(
        private readonly PopularItemService $popular,
        private readonly PeriodResolver $periodResolver,
        private readonly ApiResponder $responder,
    ) {}

    public function __invoke(MarketPopularRequest $request): JsonResponse
    {
        $period = $this->periodResolver->resolve($request->periodKey());
        $items = $this->popular->popular($request->serverId(), $period);

        return $this->responder->data(PopularItemResource::collection($items));
    }
}
