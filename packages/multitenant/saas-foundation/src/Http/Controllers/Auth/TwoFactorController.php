<?php

namespace SaasFoundation\Http\Controllers\Auth;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Authentication\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    public function show()
    {
        $user = auth()->user();
        $secret = $this->twoFactorService->generateSecret($user);
        $qrCodeUrl = $this->twoFactorService->getQrCodeUrl($user, $secret);

        return view('auth.two-factor-challenge', [
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'enabled' => $this->twoFactorService->isEnabled($user),
            'challenge' => false,
        ]);
    }

    public function challenge()
    {
        $userId = session('two_factor_user_id');

        if (! $userId || ! $user = User::find($userId)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'challenge' => true,
        ]);
    }

    public function confirm(Request $request)
    {
        $userId = session('two_factor_user_id');

        if (! $userId || ! $user = User::find($userId)) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        if ($this->twoFactorService->verifyCode($user, $request->string('code'))) {
            session()->forget('two_factor_user_id');

            auth()->login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['code' => 'The provided code is invalid.']);
    }

    public function enable(Request $request)
    {
        $user = auth()->user();
        $secret = $request->string('secret');

        $request->validate([
            'secret' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if ($this->twoFactorService->enable($user, $secret, $request->string('code'))) {
            return redirect()->route('profile.two-factor')->with('success', 'Two-factor authentication enabled.');
        }

        return back()->withErrors(['code' => 'The provided code is invalid.']);
    }

    public function disable(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if ($this->twoFactorService->disable($user, $request->string('password'))) {
            return back()->with('success', 'Two-factor authentication disabled.');
        }

        return back()->withErrors(['password' => 'Invalid password.']);
    }

    public function codes()
    {
        $user = auth()->user();

        if (! $this->twoFactorService->isEnabled($user)) {
            return redirect()->route('profile.two-factor');
        }

        $codes = $this->twoFactorService->getRecoveryCodes($user);

        return view('auth.two-factor-recovery-codes', compact('codes'));
    }

    public function regenerateCodes(Request $request)
    {
        $user = auth()->user();

        $codes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return back()->with('success', 'Recovery codes regenerated.');
    }
}
