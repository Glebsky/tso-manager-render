<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Show logs, filterable by account.
     */
    public function index(Request $request)
    {
        $query = BotLog::with('account')->latest('created_at');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        $logs     = $query->paginate(100);
        $accounts = Account::orderBy('username')->get();

        return response()->json([
            'logs'     => $logs,
            'accounts' => $accounts,
        ]);
    }
}
