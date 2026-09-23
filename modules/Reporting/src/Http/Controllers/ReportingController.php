<?php

namespace Deally\Reporting\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Models\Proposal;
use Deally\Retention\Services\RetentionService;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportingController extends Controller
{
    public function teamPerformance(Request $request, RetentionService $retention)
    {
        $this->authorizeDeally('deally.reporting.view');

        $opportunities = $this->scopeToSeat(Opportunity::query())->get();
        $calls = $this->scopeToSeat(Call::query())->with('opportunity')->get();

        $closed = $opportunities->whereIn('stage', ['won', 'lost']);
        $won = $opportunities->where('stage', 'won');

        $closeRate = $closed->count() > 0
            ? round($won->count() / $closed->count() * 100, 1)
            : 0.0;

        $avgDealSize = $won->isNotEmpty()
            ? round($won->avg('value'), 2)
            : 0.0;

        $sentiment = $calls->groupBy(fn (Call $call): string => $call->sentiment ?? 'neutral')
            ->map->count();

        $aiGuided = $this->aiGuidanceBuckets($opportunities);

        $kpis = [
            'openPipeline' => ['value' => '$'.number_format($opportunities->whereNotIn('stage', ['won', 'lost'])->sum('value')), 'sub' => $opportunities->whereNotIn('stage', ['won', 'lost'])->count().' open deals', 'href' => route('deally.pipeline')],
            'closeRate' => ['value' => $closeRate.'%', 'sub' => $won->count().' won · '.$closed->count().' closed', 'href' => route('deally.pipeline')],
            'avgDealSize' => ['value' => '$'.number_format($avgDealSize), 'sub' => 'across won deals', 'href' => route('deally.pipeline')],
            'sentiment' => ['value' => $this->netSentiment($sentiment), 'sub' => ($sentiment['positive'] ?? 0).' positive · '.($sentiment['negative'] ?? 0).' negative', 'href' => route('deally.calls.index')],
        ];

        return view('reporting::pages.team-performance', [
            'kpis' => $kpis,
            'calls' => $calls,
            'sentiment' => $sentiment,
            'aiGuided' => $aiGuided,
            'reviewCount' => Task::query()->where('title', 'like', 'Review Call —%')->where('status', '!=', 'closed')->count(),
            'archivedCount' => $retention->archivedCalls()->count(),
        ]);
    }

    public function accountStory(string $company)
    {
        $this->authorizeDeally('deally.reporting.view');

        $opportunity = $this->scopeToSeat(Opportunity::query()->where('company', $company))->first();
        $calls = $this->scopeToSeat(Call::query()->where('company', $company))->orderByDesc('date')->get();
        $proposals = $this->scopeToSeat(Proposal::query()->where('company', $company))->orderByDesc('updated_at')->get();
        $tasks = $this->scopeToSeat(Task::query()->where('linked_company', $company))->orderByDesc('created_at')->get();
        $gaps = KnowledgeGap::query()->where('source', 'like', "%{$company}%")->get();

        $sentiment = $calls->groupBy(fn (Call $call): string => $call->sentiment ?? 'neutral')->map->count();

        $unresolved = $tasks->where('status', '!=', 'closed')
            ->concat($gaps->where('status', 'pending'));

        $flags = collect();
        foreach ($tasks->where('status', '!=', 'closed') as $task) {
            if ($task->due_at !== null && $task->due_at->isPast()) {
                $flags->push("Overdue task: {$task->title} (due {$task->due_at->format('M d')})");
            }
        }
        foreach ($calls->where('sentiment', 'negative') as $call) {
            $flags->push("Negative sentiment call: {$call->name} ({$call->date->format('M d')})");
        }
        foreach ($proposals->where('status', 'rejected') as $proposal) {
            $flags->push("Proposal rejected: {$proposal->name}");
        }

        $keyNumbers = [
            'Open value' => '$'.number_format($opportunity?->value ?? $proposals->sum('value')),
            'Stage' => ucfirst($opportunity?->stage ?? '—'),
            'Conversations' => $calls->count(),
            'Proposal total' => '$'.number_format($proposals->sum('value')),
        ];

        return view('reporting::pages.account-story', [
            'company' => $company,
            'opportunity' => $opportunity,
            'calls' => $calls,
            'proposals' => $proposals,
            'tasks' => $tasks,
            'gaps' => $gaps,
            'sentiment' => $sentiment,
            'unresolved' => $unresolved,
            'flags' => $flags,
            'keyNumbers' => $keyNumbers,
        ]);
    }

    public function coachingReview(Call $call)
    {
        $this->authorizeDeally('deally.reporting.view');
        $this->authorizeSeatRecord($call);

        $call->load('transcriptLines');

        $lines = $call->transcriptLines;
        $customerLines = $lines->where('is_agent', false)->count();
        $totalLines = max(1, $lines->count());
        $talkRatio = round($customerLines / $totalLines * 100, 0);
        $inBand = $talkRatio >= 40 && $talkRatio <= 60;

        $gaps = KnowledgeGap::query()->where('source', 'like', "%{$call->company}%")->get();
        $objectionsRaised = $gaps->where('type', 'objection');
        $corrections = $gaps->where('type', 'correction');

        $moments = $this->keyMoments($lines);

        $openTasks = Task::query()
            ->where('linked_company', $call->company)
            ->where('status', '!=', 'closed')
            ->orderBy('due_at')
            ->get();

        $missedActions = $openTasks->filter(fn (Task $task): bool => $task->due_at !== null && $task->due_at->isPast())
            ->values();

        $areas = $this->coachingAreas($talkRatio, $objectionsRaised, $missedActions, $corrections);

        $score = $this->agentScore($talkRatio, $objectionsRaised, $missedActions);

        return view('reporting::pages.coaching-review', [
            'call' => $call,
            'talkRatio' => $talkRatio,
            'inBand' => $inBand,
            'objections' => $objectionsRaised,
            'corrections' => $corrections,
            'missed' => $missedActions,
            'openTasks' => $openTasks,
            'moments' => $moments,
            'areas' => $areas,
            'score' => $score,
        ]);
    }

    public function teamTasks(Request $request)
    {
        $this->authorizeDeally('deally.reporting.tasks.view');

        $tasks = $this->scopeToSeat(Task::query())->when($request->get('assignee'), function ($query, string $assignee): void {
            $query->where('assignee', $assignee);
        })->orderBy('due_at')->get();

        $assignees = $this->scopeToSeat(Task::query())->select('assignee')->distinct()->orderBy('assignee')
            ->pluck('assignee')->filter()->values();

        return view('reporting::pages.team-tasks', [
            'tasks' => $tasks,
            'assignees' => $assignees,
            'activeAssignee' => $request->get('assignee') ?? '',
        ]);
    }

    /**
     * @param  Collection<int, Opportunity>  $opportunities
     * @return array<string, mixed>
     */
    private function aiGuidanceBuckets(Collection $opportunities): array
    {
        $useBuckets = [];

        foreach ($opportunities as $opportunity) {
            $hasGuidance = $opportunity->calls()->has('transcriptLines')->exists();
            $key = $hasGuidance ? 'used' : 'notUsed';
            $useBuckets[$key] ??= ['total' => 0, 'won' => 0];
            $useBuckets[$key]['total']++;
            if ($opportunity->stage === 'won') {
                $useBuckets[$key]['won']++;
            }
        }

        $rows = [];
        foreach ([
            'used' => 'AI guidance used',
            'notUsed' => 'No AI guidance',
        ] as $key => $label) {
            if (($useBuckets[$key]['total'] ?? 0) === 0) {
                continue;
            }
            $rows[] = [
                'label' => $label,
                'total' => $useBuckets[$key]['total'],
                'won' => $useBuckets[$key]['won'],
                'rate' => round($useBuckets[$key]['won'] / $useBuckets[$key]['total'] * 100, 1),
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, int>  $sentiment */
    private function netSentiment(Collection $sentiment): string
    {
        $pos = $sentiment['positive'] ?? 0;
        $neg = $sentiment['negative'] ?? 0;
        $net = $pos - $neg;

        return match (true) {
            $net > 0 => "+{$net}",
            $net < 0 => (string) $net,
            default => '0',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function keyMoments(Collection $lines): array
    {
        $moments = [];
        $competitor = $lines->first(fn ($line): bool => $line->linked_type === 'competitor');
        $negative = $lines->firstWhere('linked_type', 'correction');
        $turnaround = $lines->last(fn ($line): bool => $line->text !== null && preg_match('/\?(?<!\))/', $line->text));

        $moments[] = [
            'label' => 'Turnaround',
            'icon' => '🔄',
            'description' => $turnaround !== null
                ? 'Buyer asked a clarifying question — review how the agent answered and whether momentum was kept.'
                : 'No obvious turnaround point — relationship stayed steady through the conversation.',
            'sequence' => $turnaround ? $turnaround->sequence : 0,
            'accent' => $turnaround ? 'good' : 'warn',
        ];

        $moments[] = [
            'label' => 'Objection Crush',
            'icon' => '💥',
            'description' => $competitor !== null
                ? 'Competitor mention surfaced — confirm the battle card was shown and the objection was answered.'
                : 'No competitor objection flagged in this conversation.',
            'sequence' => $competitor ? $competitor->sequence : 0,
            'accent' => $competitor ? 'good' : 'warn',
        ];

        $moments[] = [
            'label' => 'Missed Signal',
            'icon' => '🚩',
            'description' => $negative !== null
                ? 'An AI correction was logged — check the signal that was fixed so the pattern is reinforced.'
                : 'No correction was raised — no missed signals captured.',
            'sequence' => $negative ? $negative->sequence : 0,
            'accent' => $negative ? 'warn' : 'good',
        ];

        return $moments;
    }

    /** @return array<int, string> */
    private function coachingAreas(int $talkRatio, Collection $objections, Collection $missed, Collection $corrections): array
    {
        $areas = [];

        if (! ($talkRatio >= 40 && $talkRatio <= 60)) {
            $areas[] = 'Balance the conversation — target a 40–60% customer share (currently '.$talkRatio.'%).';
        }

        if ($objections->isEmpty()) {
            $areas[] = 'Surface objections explicitly so they can be logged and answered.';
        }

        if ($missed->isNotEmpty()) {
            $areas[] = 'Follow through on '.$missed->count().' overdue action'.($missed->count() === 1 ? '' : 's').' before the next call.';
        }

        if (! empty($areas)) {
            return array_slice($areas, 0, 3);
        }

        return ['Keep up the balanced talk ratio and keep logging objections — this call was strong.'];
    }

    private function agentScore(int $talkRatio, Collection $objections, Collection $missed): int
    {
        $score = 70;

        if ($talkRatio >= 40 && $talkRatio <= 60) {
            $score += 10;
        } elseif ($talkRatio >= 30 && $talkRatio <= 70) {
            $score += 5;
        }

        $score += min(10, $objections->count() * 3);

        $score -= min(20, $missed->count() * 6);

        return max(0, min(100, $score));
    }
}
