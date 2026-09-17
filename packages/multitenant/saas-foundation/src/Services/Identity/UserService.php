<?php

namespace SaasFoundation\Services\Identity;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class UserService
{
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? Str::random(16),
            'timezone' => $data['timezone'] ?? 'UTC',
            'locale' => $data['locale'] ?? 'en',
            'is_active' => $data['is_active'] ?? true,
            'is_super_admin' => $data['is_super_admin'] ?? false,
        ]);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function suspend(User $user): void
    {
        $user->update(['is_active' => false]);

        $user->memberships()->update(['status' => Membership::STATUS_SUSPENDED]);
    }

    public function activate(User $user): void
    {
        $user->update(['is_active' => true]);

        $user->memberships()
            ->where('status', Membership::STATUS_SUSPENDED)
            ->update(['status' => Membership::STATUS_ACTIVE]);
    }

    public function addToTenant(User $user, Tenant $tenant, array $roles = []): Membership
    {
        $membership = Membership::firstOrCreate(
            [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]
        );

        if (! empty($roles)) {
            $roleModels = Role::whereIn('id', $roles)
                ->where('tenant_id', $tenant->id)
                ->get();

            $membership->roles()->syncWithoutDetaching($roleModels->pluck('id')->toArray());
        } else {
            $defaultRole = Role::where('tenant_id', $tenant->id)
                ->where('slug', 'member')
                ->first();

            if ($defaultRole && ! $membership->roles()->where('role_id', $defaultRole->id)->exists()) {
                $membership->roles()->attach($defaultRole->id);
            }
        }

        return $membership->load('roles');
    }

    public function removeFromTenant(User $user, Tenant $tenant): void
    {
        $membership = Membership::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($membership) {
            $membership->roles()->detach();
            $membership->delete();
        }
    }

    public function getTenants(User $user): Collection
    {
        return $user->tenants()->get();
    }

    public function getMemberships(User $user): Collection
    {
        return $user->memberships()
            ->with('tenant')
            ->get();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
