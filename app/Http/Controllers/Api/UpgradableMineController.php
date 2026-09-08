<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\GameServerErrorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\UpgradableMineRequest;
use App\Http\Resources\UpgradableMineResource;
use App\Models\Account;
use App\Services\Game\Mines\MineTargetListService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UpgradableMineController extends Controller
{
    public function __construct(
        private readonly MineTargetListService $service,
    ) {}

    /**
     * @throws GameServerErrorException
     */
    public function index(UpgradableMineRequest $request): AnonymousResourceCollection
    {
        $account = Account::findOrFail($request->accountId());
        $forceRefresh = $request->refresh();
        $maxLevel = $request->maxLevel();

        $items = $this->service->upgradableMines($account, $forceRefresh, $maxLevel);

        return UpgradableMineResource::collection($items);
    }
}
