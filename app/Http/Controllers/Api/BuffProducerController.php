<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\BuffProducerRequest;
use App\Http\Resources\BuffProducerResource;
use App\Models\Account;
use App\Services\Game\Production\BuffProducerListService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuffProducerController extends Controller
{
    public function __construct(
        private readonly BuffProducerListService $service
    ) {}

    public function index(BuffProducerRequest $request): AnonymousResourceCollection
    {
        $account = Account::findOrFail($request->accountId());
        $forceRefresh = $request->refresh();

        $items = $this->service->forAccount($account, $forceRefresh);

        return BuffProducerResource::collection($items);
    }
}
