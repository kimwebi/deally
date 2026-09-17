<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Support\Str;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StoreDomainRequest;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;

class TenantDomainController extends Controller
{
    public function index(Tenant $tenant)
    {
        $domains = Domain::where('tenant_id', $tenant->id)
            ->latest()
            ->get();

        return view('tenant.domains.index', compact('tenant', 'domains'));
    }

    public function store(StoreDomainRequest $request, Tenant $tenant)
    {
        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => Str::lower($request->string('domain')),
            'type' => $request->string('type'),
            'is_primary' => $request->boolean('is_primary'),
            'is_verified' => false,
            'is_active' => false,
        ]);

        return back()->with('success', 'Domain added. Verify it to activate.');
    }

    public function destroy(Tenant $tenant, Domain $domain)
    {
        abort_if($domain->tenant_id !== $tenant->id, 404);

        if ($domain->is_primary) {
            return back()->with('error', 'The primary domain cannot be removed.');
        }

        $domain->delete();

        return back()->with('success', 'Domain removed.');
    }

    public function verify(Tenant $tenant, Domain $domain)
    {
        abort_if($domain->tenant_id !== $tenant->id, 404);

        if (! $domain->verification_token) {
            $domain->update(['verification_token' => Str::random(64)]);
        }

        return view('tenant.domains.verify', compact('tenant', 'domain'));
    }

    public function activate(Tenant $tenant, Domain $domain)
    {
        abort_if($domain->tenant_id !== $tenant->id, 404);

        $domain->update([
            'is_verified' => true,
            'is_active' => true,
        ]);

        return back()->with('success', 'Domain verified and activated.');
    }
}
