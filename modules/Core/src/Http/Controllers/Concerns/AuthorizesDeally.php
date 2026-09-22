<?php

namespace Deally\Core\Http\Controllers\Concerns;

use Deally\Core\Models\User;
use Deally\Core\Services\Seat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
