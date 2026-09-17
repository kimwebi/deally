<?php

namespace SaasFoundation\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Authentication\AuthService;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

class RegisterController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected TenantProvisioner $provisioner
    ) {}

    public function showRegistrationForm()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        if (! config('saas.auth.registration.enabled', true)) {
            return back()->with('error', 'Registration is currently disabled.');
        }

        $key = 'register:'.Str::lower($request->string('email'));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'email' => "Too many attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        RateLimiter::hit($key, 300);

        $user = $this->authService->register($validated);

        auth()->login($user);

        $request->session()->regenerate();

        if (config('saas.auth.registration.creates_tenant', true)) {
            $slug = Str::slug($request->string('tenant_name') ?: $validated['name']);

            if (! Tenant::where('slug', $slug)->exists()) {
                $tenant = $this->provisioner->provision([
                    'name' => $request->string('tenant_name') ?: $validated['name'],
                    'slug' => $slug,
                ]);

                session(['tenant_id' => $tenant->id]);
            }
        }

        return redirect()->intended(route('dashboard'));
    }
}
