<?php

namespace SaasFoundation\Services\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use SaasFoundation\Models\User;

class AuthService
{
    public function login(Request $request): ?User
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $this->validateCredentials($user, $credentials['password'])) {
            return null;
        }

        if (! $user->is_active) {
            return null;
        }

        Auth::login($user, $request->boolean('remember'));

        $this->recordLogin($user, $request);

        return $user;
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            $user->apiTokens()->delete();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
            'email_verified_at' => isset($data['email_verified']) ? now() : null,
        ]);

        return $user;
    }

    public function attemptLogin(Request $request, string $email, string $password, bool $remember = false): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! $this->validateCredentials($user, $password)) {
            return false;
        }

        if (! $user->is_active) {
            return false;
        }

        Auth::login($user, $remember);

        $this->recordLogin($user, $request);

        return true;
    }

    public function validateCredentials(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    public function recordLogin(User $user, Request $request): void
    {
        $user->update([
            'last_login_at' => now(),
        ]);

        $user->activities()->create([
            'tenant_id' => null,
            'event' => 'login',
            'description' => 'User logged in',
            'properties' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    public function getOrCreateUser(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            return $user;
        }

        return $this->register($data);
    }
}
