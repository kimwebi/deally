<?php

namespace SaasFoundation\Services\Tenancy;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;

class TenantResolver
{
    protected array $resolvers = [];

    public function register(string $name, Contracts\TenantResolverInterface $resolver): void
    {
        $this->resolvers[$name] = $resolver;
    }

    public function resolve(Request $request): ?Tenant
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolve($request);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }

    public function getResolvers(): array
    {
        return $this->resolvers;
    }
}
