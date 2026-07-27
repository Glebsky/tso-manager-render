<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        return redirect()->intended('/admin');
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

    public function register(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            // Serialize the first-user check to reduce the risk of two admins
            // being created by simultaneous registration requests.
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
            return response()->json([
                'success' => true,
                'redirect' => url('/admin'),
            ]);
        }

        return redirect('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
