<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Market Portal (Root /)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('app');
})->name('public.market');

Route::get('/market/public', function () {
    return redirect('/');
});

Route::get('/public/market', function () {
    return redirect('/');
});

Route::get('/healthz', fn () => response('ok', 200));

/*
|--------------------------------------------------------------------------
| Administration Routes (/admin/*)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.store');

        Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('register.store');
    });

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('logout');

    Route::middleware('auth:sanctum')->get('/{any?}', function () {
        return view('app');
    })->where('any', '.*');
});

// Legacy redirects
Route::get('/login', function () {
    return redirect('/admin/login');
});

Route::get('/register', function () {
    return redirect('/admin/register');
});

Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('legacy.logout');
