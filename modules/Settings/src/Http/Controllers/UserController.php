<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User as SaasFoundationUser;
use SaasFoundation\Services\Tenancy\InstanceLimiter;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorizeManageUsers();

        $tenant = $this->currentTenant();

        $members = Membership::query()
            ->forTenant($tenant->id)
            ->with(['user', 'roles'])
            ->latest()
            ->get();

        return view('settings::pages.users.index', [
            'members' => $members,
            'roles' => $this->assignableRoles($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManageUsers();

        $tenant = $this->currentTenant();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);

        $rolesToSync = $this->tenantRoleIds($data['role_ids'], $tenant);

        $user = User::where('email', $data['email'])->first();

        if ($user !== null && $this->wouldExceedInstanceLimit($user, $tenant, $rolesToSync)) {
            $limiter = app(InstanceLimiter::class);

            return back()->with('toast', "Owners are limited to {$limiter->maxPerOwner()} instances; {$user->name} already owns that many.");
        }

        if ($user === null) {
            $errors = [];

            if (blank($data['name'] ?? null)) {
                $errors['name'] = ['A name is required for new users.'];
            }

            if (blank($data['password'] ?? null)) {
                $errors['password'] = ['A password is required for new users.'];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
        }

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $membership->roles()->sync($rolesToSync);

        return back()->with('toast', "{$user->name} was added to {$tenant->name}.");
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);

        $tenant = $this->currentTenant();

        $rolesToSync = $this->tenantRoleIds($data['role_ids'], $tenant);

        if ($this->wouldExceedInstanceLimit($membership->user, $tenant, $rolesToSync)) {
            $limiter = app(InstanceLimiter::class);

            return back()->with('toast', "Owners are limited to {$limiter->maxPerOwner()} instances; cannot grant an additional owner seat.");
        }

        $membership->roles()->sync($rolesToSync);

        return back()->with('toast', "{$membership->user->name}'s roles were updated.");
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        if ($membership->user_id === auth()->id()) {
            return back()->with('toast', 'You cannot remove yourself from the tenant.');
        }

        $name = $membership->user->name;

        $membership->roles()->detach();
        $membership->delete();

        return back()->with('toast', "{$name} was removed from the tenant.");
    }

    protected function abortIfNotInTenant(Membership $membership): void
    {
        abort_if($membership->tenant_id !== $this->currentTenant()->id, 404);
    }

    protected function currentTenant(): Tenant
    {
        $tenant = auth()->user()->currentMembership?->tenant;

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    protected function authorizeManageUsers(): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $membership = $user->currentMembership;

        if (
            $membership !== null
            && ($membership->hasRole('owner') || $membership->hasRole('admin'))
        ) {
            return;
        }

        abort(403, 'You do not have permission to manage users.');
    }

    /**
     * @return Collection<int, Role>
     */
    protected function assignableRoles(Tenant $tenant): Collection
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, string>  $roleIds
     * @return array<int, string>
     */
    protected function tenantRoleIds(array $roleIds, Tenant $tenant): array
    {
        $assignable = $this->assignableRoles($tenant)->pluck('id')->all();

        return array_values(array_intersect($roleIds, $assignable));
    }

    /**
     * Whether granting the given role ids would push the user past the owned
     * instance cap of a tenant they do not already own.
     *
     * @param  array<int, string>  $roleIds
     */
    protected function wouldExceedInstanceLimit(SaasFoundationUser $user, Tenant $tenant, array $roleIds): bool
    {
        $limiter = app(InstanceLimiter::class);

        $ownerIds = Role::whereIn('slug', $limiter->ownerRoles())
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (array_intersect(array_map('strval', $roleIds), $ownerIds) === []) {
            return false;
        }

        if ($limiter->isOwner($user, $tenant)) {
            return false;
        }

        return ! $limiter->canOwn($user);
    }
}
