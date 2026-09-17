<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class AuthUserResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $membership = Membership::where('user_id', $user->id)
            ->active()
            ->with('tenant')
            ->orderByDesc('joined_at')
            ->first();

        if ($membership === null || $membership->tenant === null) {
            return null;
        }

        $tenant = $membership->tenant;

        if (! $tenant->isActive()) {
            return null;
        }

        return $tenant;
    }

    public function name(): string
    {
        return 'auth_user';
    }
}
