<?php

namespace Deally\Core\Http\Controllers\Concerns;

use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Membership;

trait AuthorizesDeally
{
    protected function deallyUser(): ?User
    {
        /** @var User|null */
        return auth()->user();
    }

    protected function deallyMembership(): ?Membership
    {
        return $this->deallyUser()?->currentMembership;
    }

    protected function deallyCan(string $permission): bool
    {
        $user = $this->deallyUser();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        $membership = $this->deallyMembership();

        if ($membership === null) {
            return false;
        }

        if ($membership->hasRole('owner') || $membership->hasRole('admin')) {
            return true;
        }

        return $membership->hasPermission($permission);
    }

    protected function authorizeDeally(string $permission): void
    {
        abort_unless($this->deallyCan($permission), 403, 'You do not have permission to access this feature.');
    }

    /**
     * User ids this member is allowed to see records for.
     *
     * @return array<int, int>|null Null means every record is visible.
     */
    protected function seatUserIds(): ?array
    {
        $user = $this->deallyUser();

        if ($user === null) {
            return null;
        }

        if ($user->isSuperAdmin() || $user->isSuperadmin() || $user->isAdmin()) {
            return null;
        }

        $membership = $this->deallyMembership();

        if ($membership === null) {
            return null;
        }

        if (
            $membership->hasRole('owner')
            || $membership->hasRole('admin')
            || $membership->hasRole('tenant-admin')
            || $membership->hasRole('solutions-lead')
        ) {
            return null;
        }

        if ($membership->hasRole('team-leader')) {
            $teamIds = Team::query()->forTenant($membership->tenant_id)->pluck('id')->all();

            $memberIds = DB::table('team_user')->whereIn('team_id', $teamIds)->pluck('user_id')->all();

            return array_values(array_unique(array_merge($memberIds, [$user->id])));
        }

        if ($membership->hasRole('sales-agent')) {
            return [$user->getKey()];
        }

        return null;
    }

    protected function scopeToSeat(Builder $query, string $column = 'owner_user_id'): Builder
    {
        $ids = $this->seatUserIds();

        if ($ids !== null) {
            $query->whereIn($column, $ids);
        }

        return $query;
    }

    protected function authorizeSeatRecord(Model $record): void
    {
        $ids = $this->seatUserIds();

        if ($ids === null) {
            return;
        }

        abort_unless(in_array($record->getAttribute('owner_user_id'), $ids, true), 403);
    }
}
