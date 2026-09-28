<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$signedInRedirect = function () {
    return auth()->user()->isSuperAdmin()
        ? redirect()->route('central.dashboard')
        : redirect()->route('deally.workspace');
};

Route::get('/', function () use ($signedInRedirect) {
    if (auth()->check()) {
        return $signedInRedirect();
    }

    return view('core::guest.login');
});

Route::get('login', function () use ($signedInRedirect) {
    if (auth()->check()) {
        return $signedInRedirect();
    }

    return view('core::guest.login');
})->name('login');

Route::post('login', function (Request $request) use ($signedInRedirect) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();

        return $signedInRedirect();
    }

    return back()->withErrors(['email' => 'These credentials do not match our records.']);
})->name('login.post');

Route::post('logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
