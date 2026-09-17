<?php

namespace SaasFoundation\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SaasFoundation\Http\Controllers\Controller;

class PasswordConfirmationController extends Controller
{
    public function show()
    {
        return view('auth.confirm-password');
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('dashboard'));
    }
}
