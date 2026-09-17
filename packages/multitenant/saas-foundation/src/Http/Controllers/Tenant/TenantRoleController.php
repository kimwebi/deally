<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StoreRoleRequest;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Authorization\RoleManager;

class TenantRoleController extends Controller
{
    public function __construct(
        protected RoleManager $roleManager
    ) {}

    public function index(Tenant $tenant)
    {
        $roles = Role::where('tenant_id', $tenant->id)
            ->withCount(['memberships'])
            ->with('permissions')
            ->latest()
            ->get();

        return view('tenant.roles.index', compact('tenant', 'roles'));
    }

    public function create(Tenant $tenant)
    {
        $permissions = Permission::all()->groupBy('group_name');

        return view('tenant.roles.create', compact('tenant', 'permissions'));
    }

    public function store(StoreRoleRequest $request, Tenant $tenant)
    {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        if ($request->filled('permissions')) {
            $role->permissions()->sync($request->input('permissions'));
        }

        return redirect()
            ->route('tenant.roles.index', $tenant)
            ->with('success', "Role '{$role->name}' created.");
    }

    public function show(Tenant $tenant, Role $role)
    {
        abort_if($role->tenant_id !== $tenant->id, 404);

        $role->load(['permissions', 'memberships.user']);

        return view('tenant.roles.show', compact('tenant', 'role'));
    }

    public function edit(Tenant $tenant, Role $role)
    {
        abort_if($role->tenant_id !== $tenant->id, 404);

        $role->load('permissions');
        $permissions = Permission::all()->groupBy('group_name');

        return view('tenant.roles.edit', compact('tenant', 'role', 'permissions'));
    }

    public function update(StoreRoleRequest $request, Tenant $tenant, Role $role)
    {
        abort_if($role->tenant_id !== $tenant->id, 404);

        $role->update([
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions') ?? []);
        }

        return redirect()
            ->route('tenant.roles.index', $tenant)
            ->with('success', "Role '{$role->name}' updated.");
    }

    public function destroy(Tenant $tenant, Role $role)
    {
        abort_if($role->tenant_id !== $tenant->id, 404);

        if ($role->isSystem()) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->memberships()->count() > 0) {
            return back()->with('error', 'Role cannot be deleted while assigned to members.');
        }

        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', "Role '{$role->name}' deleted.");
    }
}
