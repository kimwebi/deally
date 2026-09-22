<?php

namespace SaasFoundation\Services\Tenancy;

use Illuminate\Support\Facades\Config;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

/**
 * Caps how many tenant instances a single owner may own.
 *
 * The cap counts active memberships that carry an owner role (configurable
 * via `saas.instances.owner_roles`). Admins and other seat roles are not
 * counted, so a tenant owner may appoint separate administrators without
 * consuming an owned-instance slot.
 */
class InstanceLimiter
{
    public function maxPerOwner(): int
    {
        return max(1, (int) Config::get('saas.instances.max_per_owner', 10));
    }

    /**
     * Role slugs that mark membership as owning an instance.
     *
     * @return array<int, string>
     */
    public function ownerRoles(): array
    {
        $roles = Config::get('saas.instances.owner_roles', ['owner']);

        return array_values(array_filter(array_map('strval', (array) $roles)));
    }

    /**
     * Number of distinct active tenants the user currently owns.
     */
    public function ownedCount(User $user): int
    {
        return $user->memberships()
            ->active()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('slug', $this->ownerRoles());
            })
            ->distinct('tenant_id')
            ->count('tenant_id');
    }

    public function canOwn(User $user): bool
    {
        return $this->ownedCount($user) < $this->maxPerOwner();
    }

    public function isOwner(User $user, Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $user->memberships()
            ->active()
            ->forTenant($tenantId)
            ->whereHas('roles', function ($query): void {
                $query->whereIn('slug', $this->ownerRoles());
            })
            ->exists();
    }

    public function canOwnTenant(User $user, Tenant|string $tenant): bool
    {
        return $this->isOwner($user, $tenant) || $this->canOwn($user);
    }
}
