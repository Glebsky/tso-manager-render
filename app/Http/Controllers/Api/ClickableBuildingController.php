<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\ClickableBuildingRequest;
use App\Http\Resources\ClickableBuildingResource;
use App\Models\Account;
use App\Services\Game\ClickableBuildingListService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClickableBuildingController extends Controller
{
    public function __construct(
        private readonly ClickableBuildingListService $service,
    ) {}

    public function index(ClickableBuildingRequest $request): AnonymousResourceCollection
    {
        $account = Account::findOrFail($request->accountId());

        $items = $this->service->forAccount($account);

        return ClickableBuildingResource::collection($items);
    }
}
