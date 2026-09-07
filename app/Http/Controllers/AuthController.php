<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (! User::query()->exists()) {
            return redirect()->route('register');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (! User::query()->exists()) {
            return redirect()->route('register');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect('/admin');
    }

    public function showRegistration(): View|RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect()
                ->route('login')
                ->with('status', __('ui.auth.admin_registered'));
        }

        return view('app');
    }

    /**
     * @throws Throwable
     */
    public function register(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(static function () use ($validated): User {
            // In PostgreSQL, lock_for_update on an empty table does not prevent phantom inserts.
            // We acquire a transaction-level advisory lock specifically for initial registration.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT pg_advisory_xact_lock(hashtext('tso_admin_registration'))");
            }

            // Serialize the first-user check to guarantee only a single admin is created.
            if (User::query()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('ui.auth.registration_closed'),
                ]);
            }

            return User::query()->create($validated);
        });

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'success' => true,
                'redirect' => url('/admin'),
            ]);
        }

        return redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $session = $request->session();
            $session->invalidate();
            $session->regenerateToken();
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'success' => true,
                'redirect' => url('/admin/login'),
            ]);
        }

        return redirect()->route('login');
    }
}
