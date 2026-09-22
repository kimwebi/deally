<?php

namespace Deally\Core\Services;

use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Role;

/**
 * Seat visibility rules — shared between HTTP controllers and views
 * (e.g. the sidebar task count) so both always use identical scoping.
 */
class Seat
{
    /**
     * @return array<int, int>|null User ids this member may see records for.
     *                              Null means every record is visible.
     */
    public static function userIds(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $membership = $user->currentMembership;

        if ($membership === null) {
            return null;
        }

        if (
            $membership->hasRole('owner')
            || $membership->hasRole('admin')
            || $membership->hasRole('solutions-lead')
        ) {
            return null;
        }

        if ($membership->hasRole('team-leader')) {
            $teamIds = Team::query()->forTenant($membership->tenant_id)->pluck('id')->all();

            $memberIds = DB::table('team_user')->whereIn('team_id', $teamIds)->pluck('user_id')->all();

            return array_values(array_unique(array_merge($memberIds, [$user->getKey()])));
        }

        if ($membership->hasRole('sales-agent')) {
            return [$user->getKey()];
        }

        return null;
    }

    public static function scope(Builder $query, string $column = 'owner_user_id', ?User $user = null): Builder
    {
        $ids = static::userIds($user ?? auth()->user());

        if ($ids !== null) {
            $query->whereIn($column, $ids);
        }

        return $query;
    }

    /**
     * Workspace variant shown on the Home page.
     *
     * Sales agents get their personal pipeline ('self'); everyone else —
     * owners, team leaders and solutions leads — gets the team/manager
     * view ('team').
     */
    public static function view(?User $user): string
    {
        if ($user === null || $user->isSuperAdmin()) {
            return 'team';
        }

        $membership = $user->currentMembership;

        if ($membership === null) {
            return 'team';
        }

        return $membership->hasRole('sales-agent') ? 'self' : 'team';
    }

    /**
     * Human-readable primary role for the authenticated user.
     *
     * A membership may carry several roles (e.g. viewer + sales-agent); the
     * most senior one is shown in the sidebar footer.
     */
    public static function roleLabel(?User $user): string
    {
        if ($user === null) {
            return 'Member';
        }

        if ($user->isSuperAdmin()) {
            return 'Super Administrator';
        }

        $membership = $user->currentMembership;

        if ($membership === null) {
            return 'Member';
        }

        $precedence = [
            'owner' => 10,
            'admin' => 9,
            'solutions-lead' => 7,
            'team-leader' => 6,
            'sales-agent' => 5,
            'member' => 4,
            'viewer' => 3,
        ];

        $role = $membership->roles
            ->sortByDesc(fn (Role $role): int => $precedence[$role->slug] ?? 0)
            ->first();

        return $role?->name ?? 'Member';
    }
}
