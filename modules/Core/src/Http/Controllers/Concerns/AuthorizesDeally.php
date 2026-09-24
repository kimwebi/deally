<?php

namespace Deally\Core\Http\Controllers\Concerns;

use Deally\Core\Models\User;
use Deally\Core\Services\Seat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Membership;

trait AuthorizesDeally
{
    protected function deallyUser(): ?User
    {
        /** @var User|null */
        return auth()->user();
    }

    protected function deallyMembership()
    {
        return $this->deallyUser()?->currentMembership;
    }

    protected function deallyCan(string $permission): bool
    {
        $user = $this->deallyUser();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
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
        return Seat::userIds($this->deallyUser());
    }

    protected function scopeToSeat(Builder $query, string $column = 'owner_user_id'): Builder
    {
        return Seat::scope($query, $column, $this->deallyUser());
    }

    /**
     * Scope deal records by their customer's owner, so ownership always
     * inherits from the customer and never lives on the deal itself.
     */
    protected function scopeDealsToSeat(Builder $query): Builder
    {
        return Seat::scopeDeals($query, $this->deallyUser());
    }

    /**
     * Team id of the member in the current tenant — the team a new customer
     * account belongs to, via its owning agent.
     */
    protected function userTeamId(): ?string
    {
        return $this->teamIdForMember((int) ($this->deallyUser()?->getKey() ?? 0));
    }

    /**
     * Team id for a member in the current tenant, or null when they are not
     * in a team there. A customer belongs to its owning agent's team, so the
     * team follows the owner on reassignment.
     */
    protected function teamIdForMember(int $userId): ?string
    {
        $membership = $this->deallyMembership();

        if ($membership === null) {
            return null;
        }

        $teamId = DB::table('team_user')->where('user_id', $userId)->value('team_id');

        if ($teamId === null) {
            return null;
        }

        if (! DB::table('teams')->where('id', $teamId)->where('tenant_id', $membership->tenant_id)->exists()) {
            return null;
        }

        return (string) $teamId;
    }

    /**
     * Suggested (same-team) agent options for assignment controls. The team is
     * the pool suggestions are drawn from; it is never an assignment target.
     *
     * @return Collection<int, string>
     */
    protected function suggestedOwnerOptions(): Collection
    {
        $options = $this->tenantMembershipOptions();

        $ids = Seat::suggestedOwnerIds($this->deallyUser());

        return $options->filter(fn ($name, $id): bool => in_array((int) $id, $ids, true));
    }

    protected function authorizeSeatRecord(Model $record): void
    {
        $ids = $this->seatUserIds();

        if ($ids === null) {
            return;
        }

        abort_unless(in_array($record->getAttribute('owner_user_id'), $ids, true), 403);
    }

    /**
     * User ids of active memberships in the current tenant.
     *
     * @return array<int, string>
     */
    protected function tenantMemberUserIds(): array
    {
        $membership = $this->deallyMembership();

        if ($membership === null) {
            return [];
        }

        return Membership::query()
            ->forTenant($membership->tenant_id)
            ->active()
            ->pluck('user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Active tenant members as user id => name options for assignment controls.
     *
     * @return Collection<int, string>
     */
    protected function tenantMembershipOptions(): Collection
    {
        $membership = $this->deallyMembership();

        if ($membership === null) {
            return collect();
        }

        return Membership::query()
            ->forTenant($membership->tenant_id)
            ->active()
            ->with('user')
            ->get()
            ->map(fn (Membership $m) => $m->user)
            ->filter()
            ->mapWithKeys(fn ($user): array => [(int) $user->getKey() => $user->name]);
    }
}
