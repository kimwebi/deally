<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StoreTenantRequest;
use SaasFoundation\Http\Requests\UpdateTenantRequest;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

class CentralTenantController extends Controller
{
    public function __construct(
        protected TenantProvisioner $provisioner
    ) {}

    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->withCount(['memberships', 'domains', 'subscriptions'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($query) use ($request): void {
                    $search = $request->string('search');
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('trashed'), function ($query): void {
                $query->onlyTrashed();
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('central.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('central.tenants.create');
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->provisioner->provision($request->validated());

        return redirect()
            ->route('central.tenants.show', $tenant)
            ->with('success', "Tenant '{$tenant->name}' provisioned successfully.");
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount([
            'memberships',
            'projects',
            'roles',
        ]);

        $subscription = $tenant->subscriptions()
            ->with('plan', 'items.feature')
            ->latest()
            ->first();

        $members = $tenant->memberships()
            ->with(['user', 'roles'])
            ->latest()
            ->limit(10)
            ->get();

        $recentActivity = AuditLog::forTenant($tenant->id)
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('central.tenants.show', compact('tenant', 'subscription', 'members', 'recentActivity'));
    }

    public function edit(Tenant $tenant)
    {
        return view('central.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $tenant->update($request->validated());

        return redirect()
            ->route('central.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully.');
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        if (! $request->boolean('confirm')) {
            return back()->with('error', 'Please confirm tenant deletion.');
        }

        $this->provisioner->delete($tenant);

        return redirect()
            ->route('central.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' deleted successfully.");
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

        return back()->with('success', "Tenant '{$tenant->name}' suspended.");
    }

    public function restore(Tenant $tenant)
    {
        $this->provisioner->restore($tenant);

        return redirect()
            ->route('central.tenants.show', $tenant)
            ->with('success', "Tenant '{$tenant->name}' restored.");
    }

    public function clone(Tenant $tenant)
    {
        $clone = $this->provisioner->cloneForTroubleshooting($tenant);

        return redirect()
            ->route('central.tenants.show', $clone)
            ->with('success', "Troubleshooting clone '{$clone->name}' provisioned from '{$tenant->name}'.");
    }
}
