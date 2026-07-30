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
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * RESTful controller for managing player game accounts.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountSyncService $syncService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(AccountResource::collection(Account::latest()->get()));
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Account added.',
            'account' => new AccountResource($account),
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        $account->makeVisible('zone_data');

        return response()->json(new AccountResource($account));
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->deleteAccount($account);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.',
        ]);
    }

    public function sync(Account $account): JsonResponse
    {
        try {
            $zoneData = $this->syncService->sync($account);
            $freshAccount = $account->fresh();
            $freshAccount?->makeVisible('zone_data');

            return response()->json([
                'success' => true,
                'message' => 'Zone synced successfully.',
                'account' => $freshAccount !== null ? new AccountResource($freshAccount) : null,
                'zone_data' => $zoneData,
            ]);
        } catch (Exception $e) {
            $freshAccount = $account->fresh();
            $freshAccount?->makeVisible('zone_data');

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
                'account' => $freshAccount !== null ? new AccountResource($freshAccount) : null,
            ], 500);
        }
    }

    public function action(ExecuteAccountActionRequest $request, Account $account): JsonResponse
    {
        $actionType = (string) $request->input('action_type');
        $result = $this->accountService->executeAction($account, $actionType, $request->validated());

        return response()->json(
            ['success' => $result['success'], 'message' => $result['message']],
            $result['success'] ? 200 : 500
        );
    }

    public function updateSession(UpdateAccountSessionRequest $request, Account $account): JsonResponse
    {
        $account = $this->accountService->updateSession($account, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('logs.account.session_updated'),
            'account' => new AccountResource($account),
        ]);
    }

    public function friendZone(Account $account, mixed $friendId): JsonResponse
    {
        $res = $this->accountService->getFriendZone($account, (int) $friendId);

        return response()->json($res['payload'], $res['status']);
    }
}
