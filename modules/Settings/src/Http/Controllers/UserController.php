<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\Team;
use Deally\Core\Models\User;
use Deally\Core\Services\ReassignmentService;
use Deally\Pipeline\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User as SaasFoundationUser;
use SaasFoundation\Services\Tenancy\InstanceLimiter;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorizeManageUsers();

        $tenant = $this->currentTenant();

        $members = Membership::query()
            ->forTenant($tenant->id)
            ->with(['user', 'roles'])
            ->latest()
            ->get();

        return view('settings::pages.users.index', [
            'members' => $members,
            'roles' => $this->assignableRoles($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManageUsers();

        $tenant = $this->currentTenant();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);

        $rolesToSync = $this->tenantRoleIds($data['role_ids'], $tenant);

        $user = User::where('email', $data['email'])->first();

        if ($user !== null && $this->wouldExceedInstanceLimit($user, $tenant, $rolesToSync)) {
            $limiter = app(InstanceLimiter::class);

            return back()->with('toast', "Owners are limited to {$limiter->maxPerOwner()} instances; {$user->name} already owns that many.");
        }

        if ($user === null) {
            $errors = [];

            if (blank($data['name'] ?? null)) {
                $errors['name'] = ['A name is required for new users.'];
            }

            if (blank($data['password'] ?? null)) {
                $errors['password'] = ['A password is required for new users.'];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
        }

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $membership->roles()->sync($rolesToSync);

        return back()->with('toast', "{$user->name} was added to {$tenant->name}.");
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);

        $tenant = $this->currentTenant();

        $rolesToSync = $this->tenantRoleIds($data['role_ids'], $tenant);

        if ($this->wouldExceedInstanceLimit($membership->user, $tenant, $rolesToSync)) {
            $limiter = app(InstanceLimiter::class);

            return back()->with('toast', "Owners are limited to {$limiter->maxPerOwner()} instances; cannot grant an additional owner seat.");
        }

        $membership->roles()->sync($rolesToSync);

        return back()->with('toast', "{$membership->user->name}'s roles were updated.");
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        if ($membership->user_id === auth()->id()) {
            return back()->with('toast', 'You cannot remove yourself from the tenant.');
        }

        if ($this->isSalesAgent($membership)) {
            return redirect()->route('deally.users.deactivate', $membership)
                ->with('toast', 'Sales agents deactivate through the reassignment plan.');
        }

        $name = $membership->user->name;

        $this->removeMembership($membership);

        $toast = $this->isSeat($membership)
            ? "{$name} was removed. Their seat is vacant — assign someone from the people page to take over their records."
            : "{$name} was removed from the tenant.";

        return back()->with('toast', $toast);
    }

    /**
     * The deactivation screen. A departing sales agent sees the reassignment
     * plan; Team Leader / Solutions Lead seats never cascade and show
     * fill-the-seat instructions instead.
     */
    public function deactivate(Membership $membership): View
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        if ($membership->user_id === auth()->id()) {
            abort(403, 'You cannot deactivate yourself.');
        }

        $service = app(ReassignmentService::class);

        if ($this->isSalesAgent($membership)) {
            $overrides = session("reassignment_plan.{$membership->id}", []);

            $plan = $service->buildPlan($membership, $overrides);

            return view('settings::pages.users.reassignment-plan', [
                'membership' => $membership,
                'plan' => $plan,
                'agentOptions' => $service->agentOptions($membership),
                'unassignedCount' => $plan->filter(fn (array $row): bool => $row['owner_id'] === null)->count(),
                'canApprove' => $plan->every(fn (array $row): bool => $row['owner_id'] !== null),
            ]);
        }

        return view('settings::pages.users.deactivate-simple', [
            'membership' => $membership,
            'seat' => $this->isSeat($membership),
            'ownedCount' => Customer::query()->where('owner_user_id', $membership->user_id)->count(),
        ]);
    }

    public function setPlanOwner(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'owner_user_id' => ['required', 'integer'],
        ]);

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);

        abort_unless((int) $customer->owner_user_id === (int) $membership->user_id, 422);

        $allowed = app(ReassignmentService::class)->agentOptions($membership);

        abort_unless($allowed->has((int) $data['owner_user_id']), 422);

        $overrides = session("reassignment_plan.{$membership->id}", []);
        $overrides[(int) $customer->getKey()] = (int) $data['owner_user_id'];

        session(["reassignment_plan.{$membership->id}" => $overrides]);

        return back()->with('toast', "Owner override saved for {$customer->company}.");
    }

    public function assignSelected(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer'],
            'owner_user_id' => ['required', 'integer'],
        ]);

        $allowed = app(ReassignmentService::class)->agentOptions($membership);

        abort_unless($allowed->has((int) $data['owner_user_id']), 422);

        $overrides = session("reassignment_plan.{$membership->id}", []);

        foreach ($data['customer_ids'] as $customerId) {
            $customer = Customer::query()->find((int) $customerId);

            if ($customer === null || (int) $customer->owner_user_id !== (int) $membership->user_id) {
                continue;
            }

            $overrides[(int) $customer->getKey()] = (int) $data['owner_user_id'];
        }

        session(["reassignment_plan.{$membership->id}" => $overrides]);

        return back()->with('toast', 'Selected customers assigned to the chosen agent.');
    }

    public function recalculatePlan(Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        session()->forget("reassignment_plan.{$membership->id}");

        return back()->with('toast', 'Suggestions recalculated from the latest loads.');
    }

    public function approvePlan(Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        $service = app(ReassignmentService::class);

        $overrides = session("reassignment_plan.{$membership->id}", []);

        $plan = $service->buildPlan($membership, $overrides);

        $ownerMap = $plan->mapWithKeys(
            fn (array $row): array => [(int) $row['customer']->getKey() => $row['owner_id']]
        )->all();

        if (in_array(null, $ownerMap, true)) {
            return back()->with('toast', 'Every customer needs an owner before the plan can be approved.');
        }

        $transferred = $service->applyPlan($membership, $ownerMap);

        $name = $membership->user->name;

        $this->removeMembership($membership);

        session()->forget("reassignment_plan.{$membership->id}");

        $count = $transferred['customers'];

        $toast = $count > 0
            ? "{$name} was removed. {$count} customer(s) reassigned per the approved plan."
            : "{$name} was removed — they owned no customers to reassign.";

        return redirect()->route('deally.users.index')->with('toast', $toast);
    }

    /**
     * Removal confirmation for seats and non-agent members, reached from the
     * deactivation screen. Redirects to the users page so the deleted member
     * is never re-bound from a stale referer.
     */
    public function confirmRemoval(Membership $membership): RedirectResponse
    {
        $this->authorizeManageUsers();
        $this->abortIfNotInTenant($membership);

        if ($this->isSalesAgent($membership)) {
            return redirect()->route('deally.users.deactivate', $membership)
                ->with('toast', 'Sales agents deactivate through the reassignment plan.');
        }

        $name = $membership->user->name;

        $this->removeMembership($membership);

        $toast = $this->isSeat($membership)
            ? "{$name} was removed. Their seat is vacant — assign someone from the people page to take over their records."
            : "{$name} was removed from the tenant.";

        return redirect()->route('deally.users.index')->with('toast', $toast);
    }

    protected function removeMembership(Membership $membership): void
    {
        $tenantId = $membership->tenant_id;

        $membership->roles()->detach();
        $membership->delete();

        // Departing members stop being part of this tenant's teams.
        $teamIds = Team::query()->forTenant($tenantId)->pluck('id');

        if ($teamIds->isNotEmpty()) {
            DB::table('team_user')
                ->where('user_id', $membership->user_id)
                ->whereIn('team_id', $teamIds)
                ->delete();
        }
    }

    /** @return array<int, string> */
    protected function roleSlugs(Membership $membership): array
    {
        return $membership->roles()
            ->pluck('slug')
            ->map(fn (mixed $slug): string => (string) $slug)
            ->values()
            ->all();
    }

    protected function isSalesAgent(Membership $membership): bool
    {
        return in_array('sales-agent', $this->roleSlugs($membership), true);
    }

    protected function isSeat(Membership $membership): bool
    {
        return array_intersect($this->roleSlugs($membership), ReassignmentService::SEAT_ROLES) !== [];
    }

    protected function abortIfNotInTenant(Membership $membership): void
    {
        abort_if($membership->tenant_id !== $this->currentTenant()->id, 404);
    }

    protected function currentTenant(): Tenant
    {
        $tenant = auth()->user()->currentMembership?->tenant;

        if (! $tenant) {
            abort(403, 'No tenant context available.');
        }

        return $tenant;
    }

    protected function authorizeManageUsers(): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $membership = $user->currentMembership;

        if (
            $membership !== null
            && ($membership->hasRole('owner') || $membership->hasRole('admin'))
        ) {
            return;
        }

        abort(403, 'You do not have permission to manage users.');
    }

    /**
     * @return Collection<int, Role>
     */
    protected function assignableRoles(Tenant $tenant): Collection
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, string>  $roleIds
     * @return array<int, string>
     */
    protected function tenantRoleIds(array $roleIds, Tenant $tenant): array
    {
        $assignable = $this->assignableRoles($tenant)->pluck('id')->all();

        return array_values(array_intersect($roleIds, $assignable));
    }

    /**
     * Whether granting the given role ids would push the user past the owned
     * instance cap of a tenant they do not already own.
     *
     * @param  array<int, string>  $roleIds
     */
    protected function wouldExceedInstanceLimit(SaasFoundationUser $user, Tenant $tenant, array $roleIds): bool
    {
        $limiter = app(InstanceLimiter::class);

        $ownerIds = Role::whereIn('slug', $limiter->ownerRoles())
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if (array_intersect(array_map('strval', $roleIds), $ownerIds) === []) {
            return false;
        }

        if ($limiter->isOwner($user, $tenant)) {
            return false;
        }

        return ! $limiter->canOwn($user);
    }
}
