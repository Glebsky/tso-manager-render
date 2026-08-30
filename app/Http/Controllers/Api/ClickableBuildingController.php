<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClickableBuildingResource;
use App\Models\Account;
use App\Services\Game\ClickableBuildingListService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClickableBuildingController extends Controller
{
    public function __construct(
        private readonly ClickableBuildingListService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
        ]);

        $account = Account::findOrFail((int) $validated['account_id']);

        $items = $this->service->forAccount($account);

        return ClickableBuildingResource::collection($items);
    }
}
