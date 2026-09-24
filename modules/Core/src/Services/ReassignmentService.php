<?php

namespace Deally\Core\Services;

use Deally\Calls\Models\Call;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Services\RiskService;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\User;

/**
 * Ownership transfer when a member leaves the tenant.
 *
 * Only a departing sales agent triggers the reassignment flow. Team Leader
 * and Solutions Lead seats are filled manually by the tenant admin and never
 * cascade. A departing sales agent is routed through a reassignment plan the
 * admin reviews and approves: per-customer suggested owners are drawn from the
 * departing agent's own team (least-loaded first), the admin can override /
 * bulk-assign / recalculate, and approval transfers customers + the agent's
 * calls, proposals and open tasks before the membership is removed.
 *
 * Deals need no transfer — they inherit ownership from the customer, so moving
 * the customer moves every deal in its pipeline.
 */
class ReassignmentService
{
    /** Seats filled manually by the tenant admin, never cascaded. */
    public const SEAT_ROLES = ['owner', 'admin', 'team-leader', 'solutions-lead'];

    /**
     * Build the reassignment plan a tenant admin reviews before deactivating
     * a sales agent: one row per customer the agent owns, with a suggested
     * owner (least-loaded agent from the departing agent's team), urgency from
     * the risk engine, the demand-account flag, and Service Review status.
     *
     * @param  array<int, int>|null  $overrides  customer id => chosen owner id
     * @return Collection<int, array{
     *     customer: Customer,
     *     suggested_owner_id: int|null,
     *     suggested_owner_name: string|null,
     *     owner_id: int|null,
     *     urgency: string,
     *     tier: string,
     *     demand_account: bool,
     *     missed_sessions: int,
     *     service_review: string,
     * }>
     */
    public function buildPlan(Membership $departed, ?array $overrides = null): Collection
    {
        $departedId = (int) $departed->user_id;

        $customers = Customer::query()
            ->where('owner_user_id', $departedId)
            ->orderBy('company')
            ->get();

        $assessments = app(RiskService::class)->assessments($customers);

        // Running load per candidate so suggestions balance customers across
        // the pool instead of piling every account on one agent.
        $loads = $this->candidateLoads($departed);

        return $customers->map(function (Customer $customer) use ($departed, $overrides, $assessments, &$loads): array {
            $suggested = $this->leastLoadedCandidate($departed, $loads);

            $risk = $assessments[(int) $customer->getKey()] ?? [];

            $ownerId = $overrides[(int) $customer->getKey()]
                ?? ($suggested !== null ? (int) $suggested->getKey() : null);

            $schedule = $customer->activeServiceReview();

            return [
                'customer' => $customer,
                'suggested_owner_id' => $suggested !== null ? (int) $suggested->getKey() : null,
                'suggested_owner_name' => $suggested?->name,
                'owner_id' => $ownerId,
                'urgency' => $risk['label'] ?? 'Low',
                'tier' => $risk['tier'] ?? RiskService::TIER_LOW,
                'demand_account' => (bool) ($risk['demand_account'] ?? false),
                'missed_sessions' => (int) ($risk['missed_sessions'] ?? 0),
                'service_review' => $schedule?->status ?? 'none',
            ];
        })->values();
    }

    /**
     * Apply an approved plan: every customer the departing agent owns is
     * handed to its chosen owner and the agent's calls, proposals and open
     * tasks for those accounts follow. Deals inherit ownership through the
     * customer, so they need no individual transfer.
     *
     * @param  array<int, int|null>  $ownerByCustomerId
     * @return array{customers: int, calls: int, proposals: int, tasks: int}
     */
    public function applyPlan(Membership $departed, array $ownerByCustomerId): array
    {
        $departedId = (int) $departed->user_id;

        $summary = ['customers' => 0, 'calls' => 0, 'proposals' => 0, 'tasks' => 0];

        Customer::query()
            ->where('owner_user_id', $departedId)
            ->get()
            ->each(function (Customer $customer) use ($ownerByCustomerId, $departedId, &$summary): void {
                $ownerId = $ownerByCustomerId[(int) $customer->getKey()] ?? null;

                if ($ownerId === null || (int) $ownerId === $departedId) {
                    return;
                }

                $successor = User::query()->find((int) $ownerId);

                if ($successor === null) {
                    return;
                }

                $this->transferCustomerRecords($customer, $successor, $departedId, $summary);
            });

        // Records the departing agent owned on accounts that stay put (e.g. a
        // follow-up task on a teammate's customer) follow that account's
        // owner, so nothing is orphaned by the removal.
        $this->rehomeRemainingRecords($departedId, $summary);

        return $summary;
    }

    /**
     * Hand any calls/proposals/open tasks the departing agent still owns —
     * for companies that were not reassigned by the plan — to the customer
     * account owner they belong to.
     */
    protected function rehomeRemainingRecords(int $departedId, array &$summary): void
    {
        $companyOwners = Customer::query()
            ->where('owner_user_id', '!=', $departedId)
            ->pluck('owner_user_id', 'company');

        foreach (Call::query()->where('owner_user_id', $departedId)->get() as $call) {
            $owner = $companyOwners[$call->company] ?? null;

            if ($owner !== null) {
                $call->update(['owner_user_id' => (int) $owner]);
                $summary['calls']++;
            }
        }

        foreach (Proposal::query()->where('owner_user_id', $departedId)->get() as $proposal) {
            $owner = $companyOwners[$proposal->company] ?? null;

            if ($owner !== null) {
                $proposal->update(['owner_user_id' => (int) $owner]);
                $summary['proposals']++;
            }
        }

        foreach (Task::query()->where('owner_user_id', $departedId)->where('status', '!=', 'closed')->get() as $task) {
            $owner = $companyOwners[$task->linked_company] ?? null;

            if ($owner !== null) {
                $task->update(['owner_user_id' => (int) $owner]);
                $summary['tasks']++;
            }
        }
    }

    /**
     * Agents an admin may assign customers to for a departing member.
     *
     * @return Collection<int, string> agent id => name
     */
    public function agentOptions(Membership $departed): Collection
    {
        $ids = [];

        foreach ($this->candidatePools($departed) as $pool) {
            foreach ($pool as $id) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', array_values($ids))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [(int) $user->getKey() => $user->name]);
    }

    protected function transferCustomerRecords(Customer $customer, User $successor, int $departedId, array &$summary): void
    {
        $successorId = (int) $successor->getKey();

        // Team ids are UUID strings — never int-cast them or the customer
        // ends up in "team 0".
        $successorTeamId = DB::table('team_user')
            ->where('user_id', $successorId)
            ->value('team_id');

        $customer->update([
            'owner_user_id' => $successorId,
            // The customer follows its new owner's team.
            'team_id' => $successorTeamId !== null ? (string) $successorTeamId : $customer->team_id,
        ]);

        $summary['customers']++;

        $summary['calls'] += Call::query()
            ->where('company', $customer->company)
            ->where('owner_user_id', $departedId)
            ->update(['owner_user_id' => $successorId]);

        $summary['proposals'] += Proposal::query()
            ->where('company', $customer->company)
            ->where('owner_user_id', $departedId)
            ->update(['owner_user_id' => $successorId]);

        $summary['tasks'] += Task::query()
            ->where('linked_company', $customer->company)
            ->where('owner_user_id', $departedId)
            ->where('status', '!=', 'closed')
            ->update(['owner_user_id' => $successorId]);
    }

    /**
     * Candidate pools, team first (the team is where suggestions are drawn
     * from), then the tenant's other sales agents as a fallback.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    protected function candidatePools(Membership $departed): array
    {
        $departedId = (int) $departed->user_id;
        // Tenant ids are UUID strings — never int-cast them or no other
        // membership will ever look like it belongs to the tenant.
        $tenantId = $departed->tenant_id;

        $tenantAgents = Membership::query()
            ->forTenant($tenantId)
            ->active()
            ->where('user_id', '!=', $departedId)
            ->with('roles')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->hasRole('sales-agent'))
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $teamAgents = DB::table('team_user')
            ->where('user_id', $departedId)
            ->pluck('team_id')
            ->pipe(fn ($teamIds): Collection => DB::table('team_user')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', '!=', $departedId)
                ->pluck('user_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->intersect($tenantAgents)
            ->values()
            ->all();

        return [
            array_values(array_unique($teamAgents)),
            array_values(array_unique($tenantAgents)),
        ];
    }

    /**
     * @return array<int, int> candidate user id => owned customer count
     */
    protected function candidateLoads(Membership $departed): array
    {
        $loads = [];

        foreach ($this->candidatePools($departed) as $pool) {
            foreach ($pool as $id) {
                $loads[$id] = Customer::query()->where('owner_user_id', $id)->count();
            }
        }

        return $loads;
    }

    protected function leastLoadedCandidate(Membership $departed, array &$loads): ?User
    {
        foreach ($this->candidatePools($departed) as $pool) {
            if ($pool === []) {
                continue;
            }

            $pool = array_values(array_unique($pool));

            usort($pool, fn (int $a, int $b): int => ($loads[$a] ?? 0) <=> ($loads[$b] ?? 0));

            $pick = $pool[0];

            // The picked candidate's plan grows by one customer.
            $loads[$pick] = ($loads[$pick] ?? 0) + 1;

            return User::query()->find($pick);
        }

        return null;
    }
}
