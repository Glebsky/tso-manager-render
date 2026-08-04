<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Market\AnalyticsController;
use App\Http\Controllers\Market\ArbitrageController;
use App\Http\Controllers\Market\BulkController;
use App\Http\Controllers\Market\CatalogController;
use App\Http\Controllers\Market\PopularController;
use App\Http\Controllers\Market\PublicServerController;
use App\Http\Controllers\Market\ServerController;
use App\Http\Controllers\Market\SettingsController as MarketSettingsController;
use App\Http\Controllers\Market\SyncLogController;
use App\Http\Controllers\Market\VersionController;
use App\Http\Controllers\ScheduledTaskController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\HttpCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protected administration API
|--------------------------------------------------------------------------
|
| The Vue SPA authenticates with the normal Laravel session cookie. Sanctum
| validates that session for every endpoint in this group.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Accounts
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
    Route::get('/accounts/{account}/zone', [AccountController::class, 'zone']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);
    Route::post('/accounts/{account}/sync', [AccountController::class, 'sync']);
    Route::post('/accounts/{account}/action', [AccountController::class, 'action']);
    Route::put('/accounts/{account}/session', [AccountController::class, 'updateSession']);
    Route::get('/accounts/{account}/friends/{friendId}/zone', [AccountController::class, 'friendZone'])
        ->whereNumber('friendId');

    // Scheduled tasks
    Route::get('/tasks', [ScheduledTaskController::class, 'index']);
    Route::post('/tasks', [ScheduledTaskController::class, 'store']);
    Route::put('/tasks/{task}', [ScheduledTaskController::class, 'update']);
    Route::delete('/tasks/{task}', [ScheduledTaskController::class, 'destroy']);
    Route::post('/tasks/{task}/toggle', [ScheduledTaskController::class, 'toggle']);
    Route::post('/tasks/{task}/execute', [ScheduledTaskController::class, 'execute']);
    Route::get('/tasks/{task}/status', [ScheduledTaskController::class, 'status']);

    // Logs
    Route::get('/logs', [LogController::class, 'index']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::delete('/settings/logs', [SettingsController::class, 'clearLogs']);
    Route::post('/settings/tasks/stop', [SettingsController::class, 'stopAllTasks']);

    // Market server connections
    Route::get('/market/servers', [ServerController::class, 'index']);
    Route::post('/market/servers', [ServerController::class, 'store']);
    Route::put('/market/servers/{server}', [ServerController::class, 'update']);
    Route::delete('/market/servers/{server}', [ServerController::class, 'destroy']);
    Route::post('/market/servers/{server}/verify', [ServerController::class, 'verify']);
    Route::post('/market/servers/{server}/sync', [ServerController::class, 'sync']);

    // Market settings
    Route::get('/market/settings', [MarketSettingsController::class, 'index']);
    Route::put('/market/settings', [MarketSettingsController::class, 'update']);
    Route::post('/market/sync', [MarketSettingsController::class, 'sync']);
    Route::get('/market/version', VersionController::class);

    // Cached market data endpoints share the same HTTP cache contract as the
    // public API: ETag + X-Data-Version drive client-side (localStorage SWR)
    // and browser cache invalidation. Live endpoints (servers, settings,
    // logs) intentionally stay uncached.
    Route::middleware(HttpCacheHeaders::class)->group(function () {
        Route::get('/market/goods', [CatalogController::class, 'goods']);
        Route::get('/market/targets', [CatalogController::class, 'targets']);
        Route::get('/market/popular', PopularController::class);
        Route::get('/market/analytics', AnalyticsController::class);
        Route::get('/market/arbitrage', ArbitrageController::class);
        Route::get('/market/bulk', BulkController::class);
    });

    Route::get('/market/logs', SyncLogController::class);
});

/*
|--------------------------------------------------------------------------
| Public Market Analytics API
|--------------------------------------------------------------------------
*/
Route::prefix('public/market')->group(function () {
    Route::get('/version', VersionController::class);

    Route::middleware(HttpCacheHeaders::class)->group(function () {
        Route::get('/servers', PublicServerController::class);
        Route::get('/goods', [CatalogController::class, 'goods']);
        Route::get('/targets', [CatalogController::class, 'targets']);
        Route::get('/popular', PopularController::class);
        Route::get('/analytics', AnalyticsController::class);
        Route::get('/arbitrage', ArbitrageController::class);
        Route::get('/bulk', BulkController::class);
    });
});
