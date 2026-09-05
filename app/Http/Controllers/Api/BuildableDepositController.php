<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\GameServerErrorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BuildableDepositResource;
use App\Models\Account;
use App\Services\Game\Mines\MineTargetListService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildableDepositController extends Controller
{
    public function __construct(
        private readonly MineTargetListService $service,
    ) {}

    /**
     * @throws GameServerErrorException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $account = Account::findOrFail((int) $validated['account_id']);
        $forceRefresh = $request->boolean('refresh');

        $items = $this->service->buildableDeposits($account, $forceRefresh);

        return BuildableDepositResource::collection($items);
    }
}
