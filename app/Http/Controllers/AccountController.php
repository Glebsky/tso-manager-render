<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use App\Services\TsoAuthService;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Http\Request;
use Exception;

class AccountController extends Controller
{
    private TsoAuthService    $authService;
    private TsoAmfService     $amfService;
    private ZoneParserService $zoneParser;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService, ZoneParserService $zoneParser)
    {
        $this->authService = $authService;
        $this->amfService  = $amfService;
        $this->zoneParser  = $zoneParser;
    }

    /**
     * List all accounts.
     */
    public function index()
    {
        $accounts = Account::latest()->get();
        return response()->json($accounts);
    }

    /**
     * Create a new account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string',
            'region'   => 'required|string|max:10',
        ]);

        $account = Account::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Account added.',
            'account' => $account
        ], 201);
    }

    /**
     * Show account details.
     */
    public function show(Account $account)
    {
        return response()->json($account);
    }

    /**
     * Delete an account.
     */
    public function destroy(Account $account)
    {
        // Clean up cookie file
        $cookieFile = $this->authService->getCookieFile($account);
        if (file_exists($cookieFile)) {
            @unlink($cookieFile);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.'
        ]);
    }

    /**
     * Sync: login if needed, fetch zone data, parse and cache.
     */
    public function sync(Account $account)
    {
        try {
            $account->update(['status' => 'syncing']);

            // Login if tokens are missing
            if (!$this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            // Fetch zone
            $rawAmf = $this->amfService->getZone($account);
            file_put_contents(storage_path('app/debug_zone.amf'), $rawAmf);

            // Parse zone
            $zoneData = $this->zoneParser->parse($rawAmf);

            // Save
            $account->update([
                'zone_data'    => json_encode($zoneData, JSON_UNESCAPED_UNICODE),
                'last_sync_at' => now(),
                'status'       => 'online',
            ]);

            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'success',
                'message'    => 'Zone synced: ' . count($zoneData['buildings'] ?? []) . ' buildings found.',
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Zone synced successfully.',
                'zone_data' => $zoneData
            ]);
        } catch (Exception $e) {
            $account->update(['status' => 'error']);

            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'error',
                'message'    => 'Sync failed: ' . $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute an action on an account (stop_production, start_production, apply_buff, send_specialist).
     */
    public function action(Request $request, Account $account)
    {
        $request->validate([
            'action_type' => 'required|string|in:stop_production,start_production,apply_buff,send_specialist',
        ]);

        try {
            // Ensure authenticated
            if (!$this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $actionType = $request->input('action_type');
            $result     = '';

            switch ($actionType) {
                case 'stop_production':
                    $request->validate(['grid' => 'required|integer']);
                    $result = $this->amfService->stopProduction($account, (int) $request->input('grid'));
                    break;

                case 'start_production':
                    $request->validate(['grid' => 'required|integer']);
                    $result = $this->amfService->startProduction($account, (int) $request->input('grid'));
                    break;

                case 'apply_buff':
                    $request->validate([
                        'grid'       => 'required|integer',
                        'unique_id1' => 'required|integer',
                        'unique_id2' => 'required|integer',
                    ]);
                    $result = $this->amfService->applyBuff(
                        $account,
                        (int) $request->input('grid'),
                        (int) $request->input('unique_id1'),
                        (int) $request->input('unique_id2')
                    );
                    break;

                case 'send_specialist':
                    $request->validate([
                        'task_type'   => 'required|integer',
                        'sub_task_id' => 'required|integer',
                        'unique_id1'  => 'required|integer',
                        'unique_id2'  => 'required|integer',
                    ]);
                    $result = $this->amfService->sendSpecialist(
                        $account,
                        (int) $request->input('task_type'),
                        (int) $request->input('sub_task_id'),
                        (int) $request->input('unique_id1'),
                        (int) $request->input('unique_id2')
                    );
                    break;
            }

            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'success',
                'message'    => "Action [{$actionType}] executed successfully.",
            ]);

            return response()->json([
                'success' => true,
                'message' => "Action [{$actionType}] executed successfully."
            ]);
        } catch (Exception $e) {
            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'error',
                'message'    => "Action [{$request->input('action_type')}] failed: " . $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Action failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
