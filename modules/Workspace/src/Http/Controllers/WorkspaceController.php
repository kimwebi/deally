<?php

namespace Deally\Workspace\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\Opportunity;
use Deally\Proposals\Models\Proposal;
use Deally\Tasks\Models\Task;

class WorkspaceController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.workspace.view');

        $opportunities = $this->scopeToSeat(Opportunity::query())->orderByDesc('value')->get();
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

        $todayCalls = $this->scopeToSeat(Call::query())->whereDate('date', today())->get();

        $calendarEvents = collect()
            ->merge($todayCalls->map(function (Call $call): array {
                return [
                    'type' => 'call',
                    'hour' => $call->date->format('G'),
                    'label' => $call->name.' — '.$call->company,
                    'time' => $call->date->format('H:i'),
                    'href' => route('deally.calls.live', $call),
                ];
            }))
            ->merge($this->scopeToSeat(Task::query())->todo()->get()->map(function (Task $task): array {
                return [
                    'type' => 'task',
                    'hour' => '09',
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
        ]);
    }
}
