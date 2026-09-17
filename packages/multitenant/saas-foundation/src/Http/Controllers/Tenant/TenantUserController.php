<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Authentication\InvitationService;

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

        $membership->roles()->sync($validated['roles'] ?? []);

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
}
