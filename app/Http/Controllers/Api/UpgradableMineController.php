<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\GameServerErrorException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UpgradableMineResource;
use App\Models\Account;
use App\Services\Game\Mines\MineTargetListService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UpgradableMineController extends Controller
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
            'max_level' => ['nullable', 'integer', 'min:2', 'max:7'],
        ]);

        $account = Account::findOrFail((int) $validated['account_id']);
        $forceRefresh = $request->boolean('refresh');
        $maxLevel = isset($validated['max_level']) ? (int) $validated['max_level'] : null;

        $items = $this->service->upgradableMines($account, $forceRefresh, $maxLevel);

        return UpgradableMineResource::collection($items);
    }
}
