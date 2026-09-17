<?php

namespace SaasFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasConfirmedPassword()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Password confirmation required.'], 423);
            }

            return redirect()->route('password.confirm');
        }

        return $next($request);
    }
}
