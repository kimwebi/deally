<?php

namespace Deally\Calls\Services;

use Deally\Calls\Models\Call;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Support\Collection;

/**
 * The brief shown beside a live call.
 *
 * Everything here is read once, when the page loads, and does not change during
 * the call: the panel is fixed context, not a second live surface competing with
 * the AI for attention.
 *
 * The previous version of this panel was hardcoded. It told every rep the
 * customer's sentiment was trending positive, that the budget had come up four
 * times, and that a spec sheet was overdue — for every deal, invented, with no
 * connection to any record. A rep reading it during a live call had no way to
 * tell fiction from fact, which makes it worse than an empty panel. Everything
 * below is real, and every section says so plainly when it has nothing.
 */
class CustomerBrief
{
    public function __construct(
        public readonly string $contactName,
        public readonly ?string $contactRole,
        public readonly string $company,
        public readonly ?string $dealStage,
        public readonly ?float $dealValue,
        /** @var array<int, string> */
        public readonly array $proposedPackages,
        public readonly ?string $sentimentTrend,
        /** @var Collection<int, KnowledgeGap> */
        public readonly Collection $unresolvedCommitments,
        /** @var Collection<int, Call> */
        public readonly Collection $recentCalls,
        /** @var Collection<int, Proposal> */
        public readonly Collection $proposals,
        public readonly int $callCount,
    ) {}

    public static function for(Call $call): self
    {
        $opportunity = $call->opportunity;
        $company = $call->company;

        $recentCalls = Call::query()
            ->where('company', $company)
            ->whereKeyNot($call->getKey())
            ->where('status', Call::STATUS_COMPLETED)
            ->orderByDesc('date')
            ->limit(3)
            ->get();

        return new self(
            contactName: $call->contact_name ?: $opportunity?->contact_name ?: 'Customer',
            contactRole: $call->contact_role ?: $opportunity?->contact_title,
            company: $company,
            dealStage: $opportunity?->stage,
            dealValue: $opportunity?->value !== null ? (float) $opportunity->value : null,
            proposedPackages: array_values(array_filter(
                preg_split('/\s*,\s*/', (string) ($opportunity?->packages ?? '')) ?: []
            )),
            sentimentTrend: self::sentimentTrend($recentCalls),
            /* Promises made and not yet delivered are the single most useful
               thing to know going into a call, so they are read from the gap log
               rather than inferred. */
            unresolvedCommitments: KnowledgeGap::query()
                ->where('source', 'LIKE', "%{$company}%")
                ->where('status', '!=', 'resolved')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            recentCalls: $recentCalls,
            proposals: Proposal::query()
                ->where('company', $company)
                ->orderByDesc('id')
                ->limit(3)
                ->get(),
            callCount: Call::query()->where('company', $company)->count(),
        );
    }

    /**
     * A direction, not a score.
     *
     * One previous call is not a trend, so this stays null rather than
     * extrapolating from a single data point.
     *
     * @param  Collection<int, Call>  $recentCalls
     */
    protected static function sentimentTrend(Collection $recentCalls): ?string
    {
        if ($recentCalls->count() < 2) {
            return null;
        }

        $order = ['negative' => -1, 'neutral' => 0, 'positive' => 1];

        $scores = $recentCalls
            ->map(fn (Call $call): int => $order[$call->effectiveSentiment()] ?? 0)
            ->values();

        $newest = $scores->first();
        $oldest = $scores->last();

        if ($newest > $oldest) {
            return 'improving across the last '.count($scores).' calls';
        }

        if ($newest < $oldest) {
            return 'softening across the last '.count($scores).' calls';
        }

        return 'steady across the last '.count($scores).' calls';
    }

    /**
     * Open commitments, worded as the commitment rather than the raw gap text.
     *
     * @param  Collection<int, KnowledgeGap>  $gaps
     * @return array<int, array{id: int, text: string, source: ?string}>
     */
    public function commitments(): array
    {
        return $this->unresolvedCommitments
            ->map(fn (KnowledgeGap $gap): array => [
                'id' => $gap->id,
                'text' => $gap->text,
                'source' => $gap->source,
            ])
            ->all();
    }

    /**
     * @return array<int, array{date: ?string, name: string, sentiment: string, url: string}>
     */
    public function history(): array
    {
        return $this->recentCalls
            ->map(fn (Call $call): array => [
                'date' => $call->date?->format('j M Y'),
                'name' => $call->name,
                'sentiment' => $call->effectiveSentiment(),
                'url' => route('deally.calls.review', $call),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, status: string, created_at: ?string}>
     */
    public function proposalHistory(): array
    {
        return $this->proposals
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'status' => (string) $proposal->status,
                'created_at' => $proposal->created_at?->format('j M Y'),
            ])
            ->all();
    }

    /**
     * Open tasks on this company, for the rail.
     *
     * @return Collection<int, Task>
     */
    public function openTasks(): Collection
    {
        return Task::query()
            ->where('linked_company', $this->company)
            ->where('status', '!=', 'closed')
            ->orderBy('due_at')
            ->limit(6)
            ->get();
    }
}
