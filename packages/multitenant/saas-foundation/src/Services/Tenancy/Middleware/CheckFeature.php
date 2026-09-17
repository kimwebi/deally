<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Services\Tenancy\TenantContext;

class CheckFeature
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next, string $featureSlug): mixed
    {
        $checker = $this->context->feature($featureSlug);

        if ($checker->disabled()) {
            return response('This feature is not available on your current plan.', 403);
        }

        return $next($request);
    }
}
