<?php

namespace Deally\Workspace\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Services\Seat;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\KnowledgeGap;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use SaasFoundation\Models\Membership;

class WorkspaceController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.workspace.view');

        if (Seat::view($this->deallyUser()) === 'team') {
            return $this->teamWorkspace();
        }

        return $this->agentWorkspace();
    }

    public function storeEvent(Request $request)
    {
        $this->authorizeDeally('deally.tasks.manage');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:deally,external,task'],
            'title' => ['required', 'string'],
            'linked_company' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        if ($data['type'] === 'deally') {
            Call::create([
                'name' => $data['title'],
                'company' => $data['linked_company'] ?? ($this->deallyMembership()?->tenant?->name ?? 'Customer'),
                'date' => $data['due_at'] ?? now(),
                'duration' => '0m',
                'sentiment' => 'neutral',
                'status' => Call::STATUS_SCHEDULED,
                'owner_user_id' => auth()->id(),
            ]);

            return back()->with('toast', 'DeAlly call scheduled — open it from the calendar to start.');
        }

        $prefix = $data['type'] === 'external' ? '[External] ' : '';

        Task::create([
            'title' => $prefix.$data['title'],
            'linked_company' => $data['linked_company'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'status' => 'todo',
            'owner_user_id' => auth()->id(),
        ]);

        $toast = $data['type'] === 'external' ? 'External event added.' : 'Task block added.';

        return back()->with('toast', $toast);
    }

    private function agentWorkspace()
    {
        $opportunities = $this->scopeDealsToSeat(Opportunity::query())->with('customer')->orderByDesc('value')->get();
        $totalValue = $opportunities->sum('value');

        $calls = $this->scopeToSeat(Call::query())->orderByDesc('date')->take(3)->get();
        $recentProposals = $this->scopeToSeat(Proposal::query())->orderByDesc('updated_at')->take(1)->get();

        $events = collect()
            ->merge($calls->map(function (Call $call): array {
                return [
                    'type' => 'call',
                    'text' => "<strong>{$call->name}</strong> — {$call->company}",
                    'time' => $call->date->shortRelativeToNowDiffForHumans(),
                ];
            }))
            ->merge($recentProposals->map(function (Proposal $proposal): array {
                return [
                    'type' => 'proposal',
                    'text' => "<strong>{$proposal->company}</strong> — proposal {$proposal->status}",
                    'time' => $proposal->updated_at->shortRelativeToNowDiffForHumans(),
                ];
            }))
            ->take(4);

        $todos = $this->scopeToSeat(Task::query())
            ->todo()
            ->orderBy('due_at')
            ->get()
            ->groupBy(function (Task $task): string {
                return $task->due_at !== null && $task->due_at->isPast() ? 'overdue' : 'todo';
            });

        $todayCalls = $this->scopeToSeat(Call::query())
            ->whereDate('date', today())
            ->whereNot('status', Call::STATUS_COMPLETED)
            ->get();

        $calendarEvents = collect()
            ->merge($todayCalls->map(function (Call $call): array {
                return [
                    'type' => 'call',
                    'hour' => (int) $call->date->format('G'),
                    'label' => $call->name.' — '.$call->company,
                    'time' => $call->date->format('H:i'),
                    'href' => route('deally.calls.live', $call),
                ];
            }))
            ->merge($this->scopeToSeat(Task::query())->todo()->get()->map(function (Task $task): array {
                return [
                    'type' => 'task',
                    'hour' => 9,
                    'label' => $task->title,
                    'time' => $task->due_at ? $task->due_at->format('M j') : '—',
                ];
            }));

        return view('workspace::pages.workspace', [
            'opportunities' => $opportunities,
            'totalValue' => $totalValue,
            'activities' => $events,
            'todos' => $todos->get('todo', collect()),
            'overdue' => $todos->get('overdue', collect()),
            'closed' => $this->scopeToSeat(Task::query())->where('status', 'closed')->take(5)->get(),
            'calendar' => ['events' => $calendarEvents],
            'callsToday' => $todayCalls->count(),
            'deallyCallsToday' => $todayCalls->count(),
            'assignees' => $this->tenantMembershipOptions(),
        ]);
    }

    private function teamWorkspace()
    {
        $membership = $this->deallyMembership();
        $seatIds = Seat::userIds($this->deallyUser());

        $members = $membership !== null
            ? Membership::query()->forTenant($membership->tenant_id)->with('user')->get()
            : collect();

        $roster = ($seatIds !== null
            ? $members->whereIn('user_id', $seatIds)
            : $members)->map(fn (Membership $m) => $m->user)->filter()->values();

        $names = $roster->mapWithKeys(fn ($user) => [$user->getKey() => $user->name]);

        $opportunities = $this->scopeDealsToSeat(Opportunity::query())->with('customer')->get();
        $calls = $this->scopeToSeat(Call::query())->get();
        $proposals = $this->scopeToSeat(Proposal::query())->get();

        $open = $opportunities->whereNotIn('stage', ['won', 'lost']);
        $closed = $opportunities->whereIn('stage', ['won', 'lost']);
        $won = $opportunities->where('stage', 'won');

        $kpis = [
            'open' => [
                'value' => '$'.number_format($open->sum('value')),
                'sub' => $open->count().' open deals',
                'delta' => 'across the team',
            ],
            'closeRate' => [
                'value' => $closed->count() > 0 ? round($won->count() / $closed->count() * 100).'%' : '—',
                'sub' => $won->count().' won · '.$closed->count().' closed',
                'delta' => 'close rate',
            ],
        ];

        $latestCallByCompany = $calls
            ->groupBy('company')
            ->map(fn (Collection $group): ?Call => $group->sortByDesc('date')->first());

        $atRisk = $open->map(function (Opportunity $opportunity) use ($latestCallByCompany, $names): ?array {
            $latest = $latestCallByCompany->get($opportunity->company);
            $days = $latest?->date === null ? null : (int) now()->startOfDay()->diffInDays($latest->date);

            if ($days !== null && $days <= 7) {
                return null;
            }

            return [
                'title' => $opportunity->company,
                'value' => '$'.number_format($opportunity->value),
                'owner' => $names->get($opportunity->owner_user_id, 'Unassigned'),
                'days' => $days,
                'reason' => match (true) {
                    $days === null => 'No calls logged yet',
                    $latest?->sentiment === 'negative' => 'Sentiment dropped',
                    default => 'Stale pipeline',
                },
            ];
        })->filter()->values()->take(3);

        $leaderboard = $roster->map(function ($user) use ($opportunities): ?array {
            $mine = $opportunities->where('owner_user_id', $user->getKey());
            $wonCount = $mine->where('stage', 'won')->count();
            $closedCount = $mine->whereIn('stage', ['won', 'lost'])->count();
            $openMoney = $mine->whereNotIn('stage', ['won', 'lost'])->sum('value');

            if ($openMoney === 0 && $wonCount === 0) {
                return null;
            }

            return [
                'user_id' => $user->getKey(),
                'name' => $user->name,
                'first' => $this->firstName($user->name),
                'initials' => $this->initials($user->name),
                'stat' => '$'.number_format($openMoney).' · '.($closedCount > 0 ? round($wonCount / $closedCount * 100).'% win' : 'no closes'),
                'money' => $openMoney,
                'trend' => $closedCount === 0 || $wonCount / $closedCount >= 0.5 ? 'up' : 'down',
            ];
        })->filter()->sortByDesc('money')->take(3)->values();

        $colorClass = ['team-a', 'team-b', 'team-c'];
        $colorByOwner = [];
        $avatarColors = [];
        foreach ($leaderboard as $index => $entry) {
            $colorByOwner[$entry['user_id']] = $colorClass[$index] ?? 'team-a';
            $avatarColors[$index] = ['linear-gradient(135deg, #0EA5E9, #0077B5)', 'linear-gradient(135deg, #EC4899, #BE185D)', 'linear-gradient(135deg, #14B8A6, #0F766E)'][$index] ?? 'linear-gradient(135deg, #0EA5E9, #0077B5)';
        }

        $todayCalls = $this->scopeToSeat(Call::query())
            ->whereDate('date', today())
            ->whereNot('status', Call::STATUS_COMPLETED)
            ->get();

        $teamEvents = $todayCalls->map(function (Call $call) use ($colorByOwner, $names): array {
            return [
                'hour' => (int) $call->date->format('G'),
                'label' => $this->firstName($names->get($call->owner_user_id, 'Team')).' · '.$call->company,
                'time' => $call->date->format('H:i'),
                'color' => $colorByOwner[$call->owner_user_id] ?? 'team-a',
                'href' => route('deally.calls.live', $call),
            ];
        });

        $approvals = $proposals->whereIn('status', ['draft', 'viewed'])
            ->sortByDesc('updated_at')
            ->take(3)
            ->map(fn (Proposal $proposal) => [
                'id' => $proposal->id,
                'title' => $proposal->name,
                'meta' => $this->firstName($names->get($proposal->owner_user_id, 'Unassigned')).' · $'.number_format($proposal->value).' · '.$proposal->updated_at->diffForHumans(),
                'href' => route('deally.proposals.index'),
            ]);

        $flags = $this->coachingFlags($calls, $names);

        return view('workspace::pages.workspace-teams', [
            'kpis' => $kpis,
            'atRisk' => $atRisk,
            'leaderboard' => $leaderboard,
            'teamEvents' => $teamEvents,
            'callsToday' => $todayCalls->count(),
            'rosterCount' => $roster->count(),
            'approvals' => $approvals,
            'flags' => $flags,
            'avatarColors' => $avatarColors,
            'legendDots' => $colorClass,
            'assignees' => $this->tenantMembershipOptions(),
        ]);
    }

    /**
     * @param  Collection<int, Call>  $calls
     * @param  Collection<int, string>  $names  owner id => full name
     * @return Collection<int, array<string, string>>
     */
    private function coachingFlags(Collection $calls, Collection $names): Collection
    {
        $flags = collect();

        $calls->load('transcriptLines');

        $byOwner = $calls->groupBy('owner_user_id');

        foreach ($byOwner as $ownerId => $ownerCalls) {
            $name = $this->firstName($names->get((int) $ownerId, 'Agent'));
            $latest = $ownerCalls->sortByDesc('date')->first();
            $lines = $latest->transcriptLines;

            if ($lines->isNotEmpty()) {
                $agentShare = (int) round($lines->where('is_agent', true)->count() / $lines->count() * 100);

                if ($agentShare >= 70 || $agentShare <= 30) {
                    $flags->push([
                        'icon' => 'ai',
                        'title' => $name.' spoke '.$agentShare.'% on '.$latest->name,
                        'meta' => 'Talk ratio outside the 40–60% band · '.$latest->date->format('M d'),
                    ]);
                }
            }

            $recent = $ownerCalls->sortByDesc('date')->take(2);

            if ($recent->count() >= 2 && $recent->where('sentiment', 'negative')->count() === 2) {
                $flags->push([
                    'icon' => 'sentiment',
                    'title' => $name.' sentiment dropped',
                    'meta' => 'Negative in last 2 calls',
                ]);
            }
        }

        foreach (KnowledgeGap::query()->where('status', 'pending')->get() as $gap) {
            $flags->push([
                'icon' => $gap->type === 'correction' ? 'ai' : 'sentiment',
                'title' => $gap->type === 'correction' ? 'AI correction pending' : 'Missed signal · '.$gap->text,
                'meta' => $gap->source.' · needs review',
            ]);
        }

        return $flags->take(3);
    }

    private function firstName(string $name): string
    {
        return str($name)->before(' ')->toString();
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));
    }
}
