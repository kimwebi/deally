<?php

namespace SaasFoundation\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_super_admin',
    'is_platform_support',
    'is_active',
    'avatar',
    'timezone',
    'locale',
    'metadata',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_platform_support' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_enabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function tenants(): HasManyThrough
    {
        return $this->hasManyThrough(
            Tenant::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'tenant_id'
        );
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * System-owner side operator: the super-admin or any user flagged as
     * platform support. This gates the package's platform consoles (setup
     * console, platform-wide audit log), which are the shop itself and are
     * never tied to a tenant seat or a tenant membership role.
     */
    public function isPlatformOperator(): bool
    {
        return $this->isSuperAdmin() || $this->is_platform_support === true;
    }

    public function belongsToTenant(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public function getMembershipForTenant(Tenant|string $tenant): ?Membership
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function hasRoleInTenant(string $roleSlug, Tenant|string $tenant): bool
    {
        $membership = $this->getMembershipForTenant($tenant);

        return $membership ? $membership->hasRole($roleSlug) : false;
    }

    public function hasPermissionInTenant(string $permissionSlug, Tenant|string $tenant): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->getMembershipForTenant($tenant);

        return $membership ? $membership->hasPermission($permissionSlug) : false;
    }

    public function canInTenant(string $permission, Tenant|string $tenant): bool
    {
        return $this->hasPermissionInTenant($permission, $tenant);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSuperAdmin(Builder $query): Builder
    {
        return $query->where('is_super_admin', true);
    }
}
