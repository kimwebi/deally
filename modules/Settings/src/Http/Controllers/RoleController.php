<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SaasFoundation\Models\Permission;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorizeManageRoles();

        $tenant = $this->currentTenant();

        $roles = Role::query()
            ->where('slug', '!=', 'super-admin')
            ->where(function (Builder $query) use ($tenant) {
                $query->whereHas('memberships', fn (Builder $query) => $query->where('memberships.tenant_id', $tenant->id))
                    ->orWhere('tenant_id', $tenant->id);
            })
            ->with('permissions')
            ->withCount([
                'memberships' => fn (Builder $query) => $query->where('memberships.tenant_id', $tenant->id),
            ])
            ->orderBy('name')
            ->get();

        return view('settings::pages.roles.index', ['roles' => $roles]);
    }

    public function create(): View
    {
        $this->authorizeManageRoles();

        return view('settings::pages.roles.create', ['permissions' => $this->permissionGroups()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManageRoles();

        $data = $this->validated($request);

        $role = $this->currentTenant()->roles()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        return redirect()
            ->route('deally.roles.index')
            ->with('toast', "Role '{$role->name}' created.");
    }

    public function edit(Role $role): View
    {
        $this->authorizeManageRoles();
        $this->abortIfNotInTenant($role);

        return view('settings::pages.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManageRoles();
        $this->abortIfNotInTenant($role);

        $data = $this->validated($request);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        return redirect()
            ->route('deally.roles.index')
            ->with('toast', "Role '{$role->name}' updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeManageRoles();
        $this->abortIfNotInTenant($role);

        abort_if($role->isSystem(), 404);

        if ($role->memberships()->count() > 0) {
            return back()->with('toast', 'Role cannot be deleted while assigned to members.');
        }

        $role->permissions()->detach();
        $role->delete();

        return back()->with('toast', "Role '{$role->name}' deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
        ]);
    }

    protected function authorizeManageRoles(): void
    {
        $user = $this->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $membership = $user->currentMembership;

        if (
            $membership !== null
            && ($membership->hasRole('owner')
                || $membership->hasRole('admin'))
        ) {
            return;
        }

        abort(403, 'You do not have permission to manage roles.');
    }

    protected function currentTenant(): Tenant
    {
        $tenant = $this->user()->currentMembership?->tenant;

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    protected function abortIfNotInTenant(Role $role): void
    {
        abort_if($role->tenant_id !== null && $role->tenant_id !== $this->currentTenant()->id, 404);
    }

    protected function user(): User
    {
        return auth()->user();
    }

    /**
     * @return Collection<int, Permission>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::orderBy('group_name')->orderBy('name')->get();
    }
}
