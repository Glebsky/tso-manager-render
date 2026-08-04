<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for retrieving system and account bot logs.
 */
class LogController extends Controller
{
    /**
     * Show logs, filterable by account and log level.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BotLog::with('account:id,username,nickname')->latest('created_at');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        $logs = $query->paginate(100);
        $accounts = Account::select('id', 'username', 'nickname')->orderBy('username')->get();

        return BotLogResource::collection($logs)->additional([
            'accounts' => AccountResource::collection($accounts),
        ]);
    }
}
