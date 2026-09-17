<?php

namespace SaasFoundation\Services\Authentication;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use SaasFoundation\Exceptions\InvalidInvitationException;
use SaasFoundation\Exceptions\InvitationExpiredException;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Authorization\RoleManager;

class InvitationService
{
    public function __construct(
        protected RoleManager $roleManager,
    ) {}

    public function invite(string $email, Tenant $tenant, ?Role $role = null, ?User $invitedBy = null): Invitation
    {
        $existing = Invitation::where('email', $email)
            ->where('tenant_id', $tenant->id)
            ->where('status', Invitation::STATUS_PENDING)
            ->first();

        if ($existing && ! $existing->isExpired()) {
            return $existing;
        }

        return Invitation::create([
            'email' => $email,
            'tenant_id' => $tenant->id,
            'role_id' => $role?->id,
            'invited_by' => $invitedBy?->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(config('services.invitations.expire_days', 7)),
        ]);
    }

    public function accept(Invitation $invitation, User $user): void
    {
        if (! $invitation->isValid()) {
            if ($invitation->isExpired()) {
                throw new InvitationExpiredException;
            }

            throw new InvalidInvitationException('This invitation has already been used or revoked');
        }

        $existingMembership = Membership::where('user_id', $user->id)
            ->where('tenant_id', $invitation->tenant_id)
            ->first();

        if ($existingMembership) {
            $invitation->accept();

            if ($invitation->role_id && ! $existingMembership->roles()->where('role_id', $invitation->role_id)->exists()) {
                $existingMembership->roles()->attach($invitation->role_id);
            }

            return;
        }

        $membership = Membership::create([
            'user_id' => $user->id,
            'tenant_id' => $invitation->tenant_id,
            'status' => Membership::STATUS_ACTIVE,
            'invited_at' => $invitation->created_at,
            'accepted_at' => now(),
            'joined_at' => now(),
        ]);

        if ($invitation->role_id) {
            $membership->roles()->attach($invitation->role_id);
        } else {
            $defaultRole = Role::where('tenant_id', $invitation->tenant_id)
                ->where('slug', 'member')
                ->first();

            if ($defaultRole) {
                $membership->roles()->attach($defaultRole->id);
            }
        }

        $invitation->accept();
    }

    public function revoke(Invitation $invitation): void
    {
        $invitation->revoke();
    }

    public function resend(Invitation $invitation): Invitation
    {
        $invitation->update([
            'expires_at' => now()->addDays(config('services.invitations.expire_days', 7)),
            'status' => Invitation::STATUS_PENDING,
        ]);

        return $invitation->fresh();
    }

    public function expire(Invitation $invitation): void
    {
        $invitation->update([
            'status' => Invitation::STATUS_EXPIRED,
        ]);
    }

    public function getByToken(string $token): ?Invitation
    {
        return Invitation::where('token', $token)
            ->with(['tenant', 'role', 'inviter'])
            ->first();
    }

    public function getPendingForTenant(Tenant $tenant): Collection
    {
        return Invitation::where('tenant_id', $tenant->id)
            ->where('status', Invitation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->with(['role', 'inviter'])
            ->get();
    }

    public function cleanExpired(): int
    {
        return Invitation::where('status', Invitation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => Invitation::STATUS_EXPIRED]);
    }

    protected function generateToken(): string
    {
        return Str::random(64);
    }
}
