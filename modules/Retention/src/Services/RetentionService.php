<?php

namespace Deally\Retention\Services;

use Deally\Calls\Models\Call;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Models\RetentionSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RetentionService
{
    public const TIER_MONTHS = [
        'standard' => 3,
        'premium' => 6,
        'enterprise' => 12,
    ];

    public function tier(): string
    {
        return $this->setting()->tier ?: 'standard';
    }

    public function months(): int
    {
        $tier = $this->tier();

        return (int) ($this->setting()->months ?: self::TIER_MONTHS[$tier] ?? 3);
    }

    public function tierLabel(): string
    {
        return ucfirst($this->tier()).' · '.$this->months().' month'.($this->months() === 1 ? '' : 's');
    }

    public function isTranscriptArchived(Call $call): bool
    {
        if ($call->opportunity === null) {
            return false;
        }

        if (! in_array($call->opportunity->stage, ['won', 'lost'], true)) {
            return false;
        }

        return $call->date !== null
            && $call->date->isBefore(now()->subMonths($this->months()));
    }

    public function isProposalArchived(Proposal $proposal): bool
    {
        $opportunity = Opportunity::query()
            ->where('company', $proposal->company)
            ->whereIn('stage', ['won', 'lost'])
            ->first();

        if ($opportunity === null) {
            return false;
        }

        return $proposal->updated_at->isBefore(now()->subMonths($this->months()));
    }

    /**
     * Whether a closed deal's engagement content sits beyond the retention
     * window — the deal detail banner prompts the user to contact admin.
     */
    public function isDealArchived(Opportunity $opportunity): bool
    {
        if (! in_array($opportunity->stage, ['won', 'lost'], true)) {
            return false;
        }

        $latest = collect([
            $opportunity->calls()->max('date'),
            Proposal::query()->where('company', $opportunity->company)->max('updated_at'),
        ])->filter()->max();

        if ($latest === null) {
            return false;
        }

        return Carbon::parse($latest)->isBefore(now()->subMonths($this->months()));
    }

    public function archivedCalls(): Collection
    {
        return Call::query()
            ->whereHas('opportunity', fn ($query) => $query->whereIn('stage', ['won', 'lost']))
            ->get()
            ->filter(fn (Call $call): bool => $this->isTranscriptArchived($call))
            ->values();
    }

    public function archivedProposals(): Collection
    {
        return Proposal::query()
            ->get()
            ->filter(fn (Proposal $proposal): bool => $this->isProposalArchived($proposal))
            ->values();
    }

    protected function setting(): RetentionSetting
    {
        return RetentionSetting::query()->firstOrCreate(
            ['id' => 1],
            ['tier' => 'standard', 'months' => self::TIER_MONTHS['standard']],
        );
    }
}
