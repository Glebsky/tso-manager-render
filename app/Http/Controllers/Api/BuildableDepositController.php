<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\GameServerErrorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\BuildableDepositRequest;
use App\Http\Resources\BuildableDepositResource;
use App\Models\Account;
use App\Services\Game\Mines\MineTargetListService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildableDepositController extends Controller
{
    public function __construct(
        private readonly MineTargetListService $service,
    ) {}

    /**
     * @throws GameServerErrorException
     */
    public function index(BuildableDepositRequest $request): AnonymousResourceCollection
    {
        $account = Account::findOrFail($request->accountId());
        $forceRefresh = $request->refresh();

        $items = $this->service->buildableDeposits($account, $forceRefresh);

        return BuildableDepositResource::collection($items);
    }
}
