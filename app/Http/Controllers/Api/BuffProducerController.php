<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuffProducerResource;
use App\Models\Account;
use App\Services\Game\Production\BuffProducerListService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuffProducerController extends Controller
{
    public function __construct(
        private readonly BuffProducerListService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $account = Account::findOrFail((int) $validated['account_id']);
        $forceRefresh = $request->boolean('refresh');

        $items = $this->service->forAccount($account, $forceRefresh);

        return BuffProducerResource::collection($items);
    }
}
