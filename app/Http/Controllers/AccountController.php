<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ExecuteAccountActionRequest;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountSessionRequest;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\AccountSyncService;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Controller for account management and interactions.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(Account::latest()->get());
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Account added.',
            'account' => $account,
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        $account->makeVisible('zone_data');

        return response()->json($account);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->deleteAccount($account);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.',
        ]);
    }

    public function sync(Account $account, AccountSyncService $syncService): JsonResponse
    {
        try {
            $zoneData = $syncService->sync($account);
            $freshAccount = $account->fresh();
            $freshAccount?->makeVisible('zone_data');

            return response()->json([
                'success' => true,
                'message' => 'Zone synced successfully.',
                'account' => $freshAccount,
                'zone_data' => $zoneData,
            ]);
        } catch (Exception $e) {
            $freshAccount = $account->fresh();
            $freshAccount?->makeVisible('zone_data');

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: '.$e->getMessage(),
                'account' => $freshAccount,
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
            'account' => $account,
        ]);
    }

    public function friendZone(Account $account, mixed $friendId): JsonResponse
    {
        $res = $this->accountService->getFriendZone($account, (int) $friendId);

        return response()->json($res['payload'], $res['status']);
    }
}
