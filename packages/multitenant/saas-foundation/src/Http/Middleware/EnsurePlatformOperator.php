<?php

namespace SaasFoundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isPlatformOperator()) {
            abort(403, 'This area requires platform operator access.');
        }

        return $next($request);
    }
}
