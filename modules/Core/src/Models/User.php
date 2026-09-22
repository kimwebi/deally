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
}
