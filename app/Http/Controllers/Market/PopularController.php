<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Http\Requests\Market\MarketPopularRequest;
use App\Http\Resources\PopularItemResource;
use App\Services\Market\PopularItemService;
use App\Services\Market\Support\PeriodResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for retrieving popular (most traded) market items.
 */
final class PopularController extends Controller
{
    public function __construct(
        private readonly PopularItemService $popular,
        private readonly PeriodResolver $periodResolver,
    ) {}

    public function __invoke(MarketPopularRequest $request): AnonymousResourceCollection
    {
        $period = $this->periodResolver->resolve($request->periodKey());
        $items = $this->popular->popular($request->serverId(), $period);

        return PopularItemResource::collection($items);
    }
}
