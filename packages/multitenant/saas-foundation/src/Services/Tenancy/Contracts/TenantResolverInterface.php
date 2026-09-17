<?php

namespace SaasFoundation\Services\Tenancy\Contracts;

use Illuminate\Http\Request;
use SaasFoundation\Models\Tenant;

interface TenantResolverInterface
{
    public function resolve(Request $request): ?Tenant;

    public function name(): string;
}
