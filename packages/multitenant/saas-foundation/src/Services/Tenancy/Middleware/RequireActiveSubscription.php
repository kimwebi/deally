<?php

namespace SaasFoundation\Services\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasFoundation\Services\Tenancy\TenantContext;

class RequireActiveSubscription
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $subscriptionManager = $this->context->subscription();

        if ($subscriptionManager === null || ! $subscriptionManager->active()) {
            return response('An active subscription is required.', 403);
        }

        return $next($request);
    }
}
