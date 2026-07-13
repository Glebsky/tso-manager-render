<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MarketOffer;
use App\Models\MarketHistory;
use App\Models\MarketSyncLog;
use App\Services\MarketSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class MarketAnalyticsController extends Controller
{
    private MarketSyncService $syncService;

    public function __construct(MarketSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    private function getSettingsPath(): string
    {
        return 'market_settings.json';
    }

    private function loadSettings(): array
    {
        if (Storage::disk('local')->exists($this->getSettingsPath())) {
            return json_decode(Storage::disk('local')->get($this->getSettingsPath()), true) ?? [];
        }
        return [
            'account_id'              => null,
            'sync_interval'           => '15',
            'custom_interval_minutes' => 15,
        ];
    }

    private function saveSettings(array $settings): void
    {
        Storage::disk('local')->put($this->getSettingsPath(), json_encode($settings, JSON_PRETTY_PRINT));
    }

    public function getSettings()
    {
        $settings = $this->loadSettings();
        $accounts = Account::select('id', 'username', 'nickname')->latest()->get();

        $accountId = $settings['account_id'] ?? null;
        $connectionStatus = 'Disconnected';
        $lastSyncStr = 'Never';

        if ($accountId) {
            $account = Account::find($accountId);
            if ($account) {
                // If account is online, we say Connected. Otherwise use account status
                $connectionStatus = ($account->status === 'online') ? 'Connected' : 'Error';
            }

            // Find last successful sync log
            $lastLog = MarketSyncLog::where('account_id', $accountId)
                ->where('status', 'SUCCESS')
                ->latest()
                ->first();
            if ($lastLog) {
                $lastSyncStr = $lastLog->created_at->format('d.m.Y H:i:s');
            }
        }

        return response()->json([
            'settings'          => $settings,
            'accounts'          => $accounts,
            'connection_status' => $connectionStatus,
            'last_sync'         => $lastSyncStr,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'account_id'              => 'nullable|integer|exists:accounts,id',
            'sync_interval'           => 'required|string|in:5,15,30,60,custom',
            'custom_interval_minutes' => 'nullable|integer|min:1',
        ]);

        $this->saveSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Market settings updated.',
        ]);
    }

    public function syncNow(Request $request)
    {
        $settings = $this->loadSettings();
        $accountId = $settings['account_id'] ?? null;

        if (empty($accountId)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select an account in Settings first.',
            ], 422);
        }

        $account = Account::find($accountId);
        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Configured account not found.',
            ], 422);
        }

        try {
            $result = $this->syncService->sync($account);
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Synchronization failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getGoods()
    {
        // Get list of unique items that are available for sale
        $goods = MarketOffer::select('item_id', 'item_name')
            ->distinct()
            ->orderBy('item_name')
            ->get();

        return response()->json($goods);
    }

    public function getTargets(Request $request)
    {
        $itemId = $request->input('item_id');

        if (empty($itemId)) {
            return response()->json([]);
        }

        // Get target items that are traded for the selected item
        $targets = MarketOffer::where('item_id', $itemId)
            ->select('target_item_id', 'target_item_name')
            ->distinct()
            ->orderBy('target_item_name')
            ->get();

        return response()->json($targets);
    }

    public function getAnalytics(Request $request)
    {
        $itemId = $request->input('item_id');
        $targetItemId = $request->input('target_item_id');

        // 1. Most popular items (if pair is not selected)
        $popular = MarketOffer::selectRaw('item_id, item_name, count(*) as offers_count, count(distinct player_id) as sellers_count, sum(volume) as total_volume')
            ->groupBy('item_id', 'item_name')
            ->orderBy('offers_count', 'desc')
            ->orderBy('total_volume', 'desc')
            ->limit(10)
            ->get();

        if (empty($itemId) || empty($targetItemId)) {
            return response()->json([
                'popular' => $popular,
            ]);
        }

        // 2. Active offers stats
        $stats = MarketOffer::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
            ->first();

        // Latest price (current)
        $current = MarketOffer::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->orderBy('created_at', 'desc')
            ->value('price');

        // 3. Historical data
        $history = MarketHistory::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->selectRaw("collected_at, avg(price) as price, sum(volume) as volume, count(distinct player_id) as sellers_count, count(*) as offers_count")
            ->groupBy('collected_at')
            ->orderBy('collected_at', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'collected_at'  => $item->collected_at->format('d.m.Y H:i'),
                    'price'         => round($item->price, 2),
                    'volume'        => (int)$item->volume,
                    'sellers_count' => (int)$item->sellers_count,
                    'offers_count'  => (int)$item->offers_count,
                ];
            });

        return response()->json([
            'popular' => $popular,
            'stats'   => [
                'average' => round($stats->average_price ?? 0, 2),
                'minimum' => round($stats->min_price ?? 0, 2),
                'maximum' => round($stats->max_price ?? 0, 2),
                'current' => round($current ?? 0, 2),
            ],
            'history' => $history,
        ]);
    }

    public function getLogs()
    {
        $logs = MarketSyncLog::orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'date'    => $log->created_at->format('d.m.Y H:i:s'),
                    'action'  => $log->action,
                    'status'  => $log->status,
                    'message' => $log->message,
                ];
            });

        return response()->json($logs);
    }
}
