<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AccountSyncException;
use App\Http\Requests\Account\ExecuteAccountActionRequest;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountSessionRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\AccountSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * RESTful controller for managing player game accounts.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountSyncService $syncService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $accounts = Account::withExists('marketServerConnections')->latest()->get();

        return AccountResource::collection($accounts)
            ->additional([
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ]);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create($request->validated());

        return AccountResource::make($account)
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
        return AccountResource::make($account)->withZoneData();
    }

    public function zone(Account $account): JsonResponse
    {
        return new JsonResponse([
            'account_id' => $account->id,
            'zone_data' => $account->snapshot()->toArray(),
        ]);
    }

    public function destroy(Account $account): Response
    {
        $this->accountService->deleteAccount($account);

        return response()->noContent();
    }

    /**
     * @throws AccountSyncException
     */
    public function sync(Account $account): AccountResource
    {
        $this->syncService->sync($account);
        $freshAccount = $account->fresh() ?? $account;

        return AccountResource::make($freshAccount)->withZoneData();
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
        $updatedAccount = $this->accountService->updateSession($account, $request->sessionData());

        return new AccountResource($updatedAccount);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function friendZone(Account $account, int|string $friendId): JsonResponse
    {
        $res = $this->accountService->getFriendZone($account, (int) $friendId);

        return new JsonResponse($res['payload'], $res['status']);
    }
}
