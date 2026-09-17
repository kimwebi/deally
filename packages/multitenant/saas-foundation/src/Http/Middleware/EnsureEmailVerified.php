<?php

namespace SaasFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user->email_verified_at === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Email verification required.'], 403);
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
