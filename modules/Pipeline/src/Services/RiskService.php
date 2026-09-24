<?php

namespace Deally\Pipeline\Services;

use Deally\Calls\Models\Call;
use Deally\Pipeline\Models\AccountSetting;
use Deally\Pipeline\Models\Customer;
use Deally\Pipeline\Models\Opportunity;
use Deally\Pipeline\Models\ServiceReviewSession;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Customer-level risk tiers, computed on demand — DeAlly never stores the
 * tier, so it always reflects current data.
 *
 * Critical — a Missed Service Review session, or a proposal awaiting action
 *            with no other owner to act on it.
 * High     — a Demand Account (open pipeline value above the tenant's Account
 *            Settings threshold) with a deal in the negotiation/proposal stage.
 * Medium   — at least one open deal, not otherwise Critical or High.
 * Low      — no open deals, or no activity logged in 60+ days.
 *
 * Deals mirror their customer's tier: the at-risk badge on a deal row is a
 * read-only reflection of the customer-level flag.
 */
class RiskService
{
    public const TIER_CRITICAL = 'critical';

    public const TIER_HIGH = 'high';

    public const TIER_MEDIUM = 'medium';

    public const TIER_LOW = 'low';

    public const URGENCY_LABELS = [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    protected const INACTIVE_DAYS = 60;

    public function tierLabel(string $tier): string
    {
        return self::URGENCY_LABELS[$tier] ?? self::URGENCY_LABELS[self::TIER_LOW];
    }

    /**
     * Tier per customer id, in the same order as the given customers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assessments(Collection $customers): Collection
    {
        $threshold = AccountSetting::current()->demandThreshold();

        return $customers->mapWithKeys(fn (Customer $customer): array => [
            (int) $customer->getKey() => $this->assess($customer, $threshold),
        ]);
    }

    /**
     * @return array{
     *     tier: string,
     *     label: string,
     *     reasons: array<int, string>,
     *     at_risk: bool,
     *     demand_account: bool,
     *     missed_sessions: int,
     *     open_value: int,
     *     open_deals: int,
     * }
     */
    public function assess(Customer $customer, ?int $demandThreshold = null): array
    {
        $threshold = $demandThreshold ?? AccountSetting::current()->demandThreshold();

        $openDeals = $customer->opportunities()->whereNotIn('stage', ['won', 'lost'])->get();
        $openValue = (int) $openDeals->sum('value');

        $missedCount = ServiceReviewSession::query()
            ->whereHas('schedule', fn ($query) => $query->where('customer_id', $customer->getKey()))
            ->where('status', ServiceReviewSession::STATUS_SCHEDULED)
            ->where('scheduled_at', '<', now())
            ->count();

        $pendingProposal = Proposal::query()
            ->where('company', $customer->company)
            ->whereNotIn('status', ['approved', 'rejected'])
            ->orderByDesc('updated_at')
            ->first();

        $isDemand = $openValue > $threshold;
        $inLaterStage = $openDeals->contains(
            fn (Opportunity $deal): bool => in_array($deal->stage, ['negotiation', 'proposal'], true)
        );

        $tier = self::TIER_LOW;
        $reasons = [];

        if ($missedCount > 0) {
            $tier = self::TIER_CRITICAL;
            $reasons[] = $missedCount.' missed Service Review session'.($missedCount === 1 ? '' : 's');
        }

        if ($pendingProposal !== null) {
            $tier = self::TIER_CRITICAL;
            $reasons[] = "Proposal '{$pendingProposal->name}' is {$pendingProposal->status} and needs an owner to act on it";
        }

        if ($isDemand && $inLaterStage) {
            $tier = self::TIER_HIGH;
            $reasons[] = 'Demand account with a deal in the negotiation stage';
        }

        if ($tier === self::TIER_LOW && $openDeals->isNotEmpty()) {
            $tier = self::TIER_MEDIUM;
            $reasons[] = 'Active pipeline with open deals';
        }

        if ($tier === self::TIER_MEDIUM && $this->isInactive($customer)) {
            $tier = self::TIER_LOW;
            $reasons[] = 'No activity logged in '.self::INACTIVE_DAYS.'+ days';
        }

        return [
            'tier' => $tier,
            'label' => self::URGENCY_LABELS[$tier],
            'reasons' => $reasons,
            'at_risk' => in_array($tier, [self::TIER_CRITICAL, self::TIER_HIGH], true),
            'demand_account' => $isDemand,
            'missed_sessions' => $missedCount,
            'open_value' => $openValue,
            'open_deals' => $openDeals->count(),
        ];
    }

    protected function isInactive(Customer $customer): bool
    {
        $latest = collect([
            Call::query()->where('company', $customer->company)->max('date'),
            Proposal::query()->where('company', $customer->company)->max('updated_at'),
            Task::query()->where('linked_company', $customer->company)->max('updated_at'),
            $customer->opportunities()->max('updated_at'),
        ])->filter()->max();

        if ($latest === null) {
            return true;
        }

        return Carbon::parse($latest)->isBefore(now()->subDays(self::INACTIVE_DAYS));
    }
}
