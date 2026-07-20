<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MarketAnalyticsController;
use App\Http\Controllers\ScheduledTaskController;
use App\Http\Controllers\SettingsController;
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

    // Logs
    Route::get('/logs', [LogController::class, 'index']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::delete('/settings/logs', [SettingsController::class, 'clearLogs']);
    Route::post('/settings/tasks/stop', [SettingsController::class, 'stopAllTasks']);

    // Market analytics
    Route::get('/market/settings', [MarketAnalyticsController::class, 'getSettings']);
    Route::put('/market/settings', [MarketAnalyticsController::class, 'updateSettings']);
    Route::post('/market/sync', [MarketAnalyticsController::class, 'syncNow']);
    Route::get('/market/goods', [MarketAnalyticsController::class, 'getGoods']);
    Route::get('/market/targets', [MarketAnalyticsController::class, 'getTargets']);
    Route::get('/market/analytics', [MarketAnalyticsController::class, 'getAnalytics']);
    Route::get('/market/arbitrage', [MarketAnalyticsController::class, 'getArbitrage']);
    Route::get('/market/logs', [MarketAnalyticsController::class, 'getLogs']);
});

/*
|--------------------------------------------------------------------------
| Public Market Analytics API
|--------------------------------------------------------------------------
*/
Route::prefix('public/market')->group(function () {
    Route::get('/goods', [MarketAnalyticsController::class, 'getGoods']);
    Route::get('/targets', [MarketAnalyticsController::class, 'getTargets']);
    Route::get('/analytics', [MarketAnalyticsController::class, 'getAnalytics']);
    Route::get('/arbitrage', [MarketAnalyticsController::class, 'getArbitrage']);
});
