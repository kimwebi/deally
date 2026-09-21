<?php

namespace Deally\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use SaasFoundation\Models\User as SaasFoundationUser;

class User extends SaasFoundationUser
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }

    public function getIsAdminAttribute(): bool
    {
        return (bool) ($this->attributes['is_admin'] ?? false);
    }

    public function getIsSuperadminAttribute(): bool
    {
        return (bool) ($this->attributes['is_superadmin'] ?? false);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isSuperadmin(): bool
    {
        return $this->is_superadmin;
    }
}
