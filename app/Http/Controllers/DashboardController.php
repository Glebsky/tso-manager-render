<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;

class DashboardController extends Controller
{
    public function index()
    {
        $accounts = Account::withCount('scheduledTasks')->get();
        $logs = BotLog::with('account')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $stats = [
            'total_accounts' => $accounts->count(),
            'active_tasks' => ScheduledTask::where('is_active', true)->count(),
            'today_actions' => BotLog::where('created_at', '>=', now()->startOfDay())->count(),
            'errors' => BotLog::where('level', 'error')->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        return response()->json([
            'accounts' => $accounts,
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }
}
