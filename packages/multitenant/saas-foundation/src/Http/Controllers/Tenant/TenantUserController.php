<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Authentication\InvitationService;
use SaasFoundation\Services\Tenancy\InstanceLimiter;

class TenantUserController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        $members = Membership::forTenant($tenant->id)
            ->with(['user', 'roles'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->whereHas('user', function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role_id'), function ($query) use ($request): void {
                $query->whereHas('roles', function ($query) use ($request): void {
                    $query->where('roles.id', $request->integer('role_id'));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $roles = Role::where('tenant_id', $tenant->id)->get();

        return view('tenant.users.index', compact('tenant', 'members', 'roles'));
    }

    public function store(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $invitation = app(InvitationService::class)->invite(
            $validated['email'],
            $tenant,
            Role::find($request->input('roles.0')),
            auth()->user()
        );

        return back()->with('success', "Invitation sent to {$validated['email']}.");
    }

    public function show(Request $request, Tenant $tenant, Membership $membership)
    {
        abort_if($membership->tenant_id !== $tenant->id, 404);

        $membership->load(['user', 'roles']);

        $activity = $membership->user->activities()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('tenant.users.show', compact('tenant', 'membership', 'activity'));
    }

    public function update(Request $request, Tenant $tenant, Membership $membership)
    {
        abort_if($membership->tenant_id !== $tenant->id, 404);

        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'status' => ['nullable', 'in:active,suspended,inactive'],
        ]);

        $roleIds = $validated['roles'] ?? [];

        if ($this->grantsOwnership($membership, $roleIds)) {
            $limiter = app(InstanceLimiter::class);

            if (! $limiter->canOwnTenant($membership->user, $tenant)) {
                return back()->with('error', "Owners are limited to {$limiter->maxPerOwner()} instances; this membership would exceed the limit.");
            }
        }

        $membership->roles()->sync($roleIds);

        if (isset($validated['status'])) {
            $membership->update(['status' => $validated['status']]);
        }

        return back()->with('success', 'Membership updated successfully.');
    }

    public function destroy(Request $request, Tenant $tenant, Membership $membership)
    {
        abort_if($membership->tenant_id !== $tenant->id, 404);

        if ($membership->user_id === auth()->id()) {
            return back()->with('error', 'You cannot remove yourself from this tenant.');
        }

        $membership->roles()->detach();
        $membership->delete();

        return redirect()
            ->route('tenant.users.index', $tenant)
            ->with('success', 'Member removed from tenant.');
    }

    /**
     * Whether the given role ids would newly grant ownership of this instance.
     *
     * @param  array<int, int|string>  $roleIds
     */
    protected function grantsOwnership(Membership $membership, array $roleIds): bool
    {
        $limiter = app(InstanceLimiter::class);

        $ownerIds = Role::whereIn('slug', $limiter->ownerRoles())
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (array_intersect(array_map('strval', $roleIds), $ownerIds) === []) {
            return false;
        }

        return $membership->roles()
            ->whereIn('slug', $limiter->ownerRoles())
            ->doesntExist();
    }
}
