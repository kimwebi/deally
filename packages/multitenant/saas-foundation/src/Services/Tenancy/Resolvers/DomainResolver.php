<?php

namespace SaasFoundation\Services\Tenancy\Resolvers;

use Illuminate\Http\Request;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\Contracts\TenantResolverInterface;

class DomainResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();

        if (empty($host)) {
            return null;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($host === $appHost) {
            return null;
        }

        $domain = Domain::where('domain', $host)
            ->active()
            ->verified()
            ->with('tenant')
            ->first();

        if ($domain === null || $domain->tenant === null) {
            return null;
        }

        $tenant = $domain->tenant;

        if (! $tenant->isActive()) {
            return null;
        }

        return $tenant;
    }

    public function name(): string
    {
        return 'domain';
    }
}
