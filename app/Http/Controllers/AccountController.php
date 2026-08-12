<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ExecuteAccountActionRequest;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountSessionRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\AccountSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * RESTful controller for managing player game accounts.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountSyncService $syncService,
    ) {}

    public function index(): mixed
    {
        return AccountResource::collection(Account::latest()->get())
            ->additional([
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ]);
    }

    public function store(StoreAccountRequest $request): mixed
    {
        $account = Account::create($request->validated());

        return (new AccountResource($account))
            ->additional([
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account): AccountResource
    {
        return (new AccountResource($account))->withZoneData();
    }

    public function zone(Account $account): JsonResponse
    {
        $raw = $account->zone_data;
        $zoneData = json_decode((string) $raw, true) ?? ['buildings' => [], 'specialists' => [], 'buffs' => []];

        return new JsonResponse([
            'account_id' => $account->id,
            'zone_data' => $zoneData,
        ]);
    }

    public function destroy(Account $account): Response
    {
        $this->accountService->deleteAccount($account);

        return response()->noContent();
    }

    public function sync(Account $account): AccountResource
    {
        $this->syncService->sync($account);
        $freshAccount = $account->fresh() ?? $account;

        return (new AccountResource($freshAccount))->withZoneData();
    }

    public function action(ExecuteAccountActionRequest $request, Account $account): JsonResponse
    {
        $actionType = (string) $request->input('action_type');
        $result = $this->accountService->executeAction($account, $actionType, $request->validated());

        return new JsonResponse(
            ['success' => $result['success'], 'message' => $result['message']],
            $result['success'] ? 200 : 500
        );
    }

    public function updateSession(UpdateAccountSessionRequest $request, Account $account): AccountResource
    {
        $updatedAccount = $this->accountService->updateSession($account, $request->validated());

        return new AccountResource($updatedAccount);
    }

    public function friendZone(Account $account, int|string $friendId): JsonResponse
    {
        $res = $this->accountService->getFriendZone($account, (int) $friendId);

        return new JsonResponse($res['payload'], $res['status']);
    }
}
