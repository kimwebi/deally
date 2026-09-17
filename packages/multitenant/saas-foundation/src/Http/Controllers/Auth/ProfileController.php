<?php

namespace SaasFoundation\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Services\Identity\UserService;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function edit(Request $request)
    {
        $route = $request->string('tab', 'profile');

        return view('auth.profile', [
            'tab' => $route,
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'timezone' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
        ] + (config('saas.auth.email_verification', true)
            ? ['email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id]]
            : []));

        $this->userService->update($user, $validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function password(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        $user = $request->user();

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->memberships()->detach();
        $user->apiTokens()->delete();
        $user->delete();

        return redirect()->route('welcome')
            ->with('success', 'Your account has been deleted.');
    }
}
