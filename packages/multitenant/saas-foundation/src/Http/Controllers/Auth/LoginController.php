<?php

namespace SaasFoundation\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\LoginRequest;
use SaasFoundation\Services\Authentication\AuthService;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLoginForm()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $key = 'login:'.Str::lower($request->string('email'));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withErrors([
                    'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
                ])
                ->onlyInput('email');
        }

        $user = $this->authService->login($request);

        if (! $user) {
            RateLimiter::hit($key, 300);

            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        if (! config('saas.two_factor.enabled', false)) {
            return redirect()->intended(route('dashboard'));
        }

        if ($user->two_factor_enabled_at) {
            session(['two_factor_user_id' => $user->id]);

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request);

        return redirect()->route('login');
    }
}
