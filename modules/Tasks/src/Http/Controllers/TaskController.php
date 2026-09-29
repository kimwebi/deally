<?php

namespace Deally\Tasks\Http\Controllers;

use Closure;
use Deally\Calls\Models\Call;
use Deally\Calls\Services\CallReviewBrief;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Services\ActivityLogger;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaskController extends Controller
{
    public function index()
    {
        $this->authorizeDeally('deally.tasks.view');

        $tasks = $this->scopeToSeat(Task::query())->orderBy('created_at', 'desc')->get();

        $groups = [
            'todo' => $tasks->where('status', '!=', 'closed')->whereNotNull('due_at')->where('due_at', '>=', now()),
            'overdue' => $tasks->where('status', '!=', 'closed')->whereNull('due_at')->concat($tasks->where('status', '!=', 'closed')->whereNotNull('due_at')->where('due_at', '<', now())),
            'closed' => $tasks->where('status', 'closed'),
        ];

        /* Each task now points at the call it is actually about. The previous
           company-name match handed every task for a company the most recent
           call's review, so reviewing an old call meant reading a new one. */
        $calls = $this->scopeToSeat(Call::query())
            ->whereIn('id', $tasks->pluck('call_id')->filter())
            ->get()
            ->keyBy('id');

        // Tasks raised before the linkage existed still resolve by company.
        $orphanCalls = $this->scopeToSeat(Call::query())
            ->whereIn('company', $tasks->whereNull('call_id')->pluck('linked_company')->filter()->unique())
            ->orderByDesc('date')
            ->get();

        $reviewUrls = $tasks->mapWithKeys(function (Task $task) use ($calls, $orphanCalls): array {
            $call = $task->call_id !== null
                ? $calls->get($task->call_id)
                : $orphanCalls->firstWhere('company', $task->linked_company);

            return $call === null ? [] : [$task->id => route('deally.calls.review', $call)];
        });

        $blockedTasks = $tasks
            ->filter(fn (Task $task): bool => $task->status !== 'closed' && $task->blockedReason() !== null)
            ->keyBy('id');

        return view('tasks::pages.tasks', [
            'tasks' => $tasks,
            'reviewUrls' => $reviewUrls,
            'blockedTasks' => $blockedTasks,
            'assignees' => $this->tenantMembershipOptions(),
            'suggestedOwners' => $this->suggestedOwnerOptions(),
            'todoCount' => $tasks->where('status', '!=', 'closed')->count(),
            'overdueCount' => $tasks->where('status', '!=', 'closed')->filter(fn (Task $task): bool => $task->due_at !== null && $task->due_at->isPast())->count(),
            'reviewContext' => $this->reviewContext($tasks->whereNotNull('call_id')->pluck('call_id')->all()),
        ]);
    }

    /**
     * The review payload each call-review task needs, loaded once for the list.
     *
     * A review task is not a reminder with a title. It holds the AI's read that
     * the rep can correct, the objections they can add, the actions the AI
     * thinks were missed, and the deal flag that keeps it from being closed.
     * None of that was reachable from the list, which is where the rep actually
     * meets the task.
     *
     * @param  array<int, int>  $callIds
     * @return Collection<int, CallReviewBrief>
     */
    protected function reviewContext(array $callIds)
    {
        return CallReviewBrief::forCalls($callIds);
    }

    /**
     * One review task, rendered on its own.
     *
     * Used by the call summary page, which is where a rep lands immediately
     * after hanging up — sending them to the tasks list to find the task that
     * page just created is the one place the flow must not require a detour.
     */
    public function show(Task $task)
    {
        $this->authorizeDeally('deally.tasks.view');
        $this->authorizeSeatRecord($task);

        return response()->view('tasks::partials.review-task-modal', [
            'task' => $task,
            'brief' => $task->call_id !== null
                ? CallReviewBrief::forCalls([$task->call_id])->get($task->call_id)
                : null,
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.tasks.manage');

        $data = $request->validate([
            'title' => ['required', 'string'],
            'assignee' => ['nullable', 'string'],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'linked_company' => ['nullable', 'string'],
            'due_at' => ['required', 'date', $this->requiresDueTime()],
        ]);

        $assigneeId = $data['assignee_user_id'] ?? null;

        if ($assigneeId !== null && ! in_array((string) $assigneeId, $this->tenantMemberUserIds(), true)) {
            $assigneeId = null;
        }

        $attributes = $data;
        unset($attributes['assignee_user_id']);

        if ($assigneeId !== null) {
            $attributes['assignee'] = $this->tenantMembershipOptions()->get((int) $assigneeId);
        } elseif (blank($attributes['assignee'] ?? null)) {
            $attributes['assignee'] = auth()->user()->name;
        }

        Task::create($attributes + ['status' => 'todo', 'owner_user_id' => $assigneeId ?: auth()->id()]);

        app(ActivityLogger::class)->log(
            'task.created',
            "Task '{$data['title']}' created".(isset($data['linked_company']) ? " for {$data['linked_company']}" : '').'.'
        );

        return back()->with('toast', 'Task created.');
    }

    public function toggle(Task $task)
    {
        $this->authorizeDeally('deally.tasks.manage');
        $this->authorizeSeatRecord($task);

        if ($task->status !== 'closed') {
            /* A review cannot be signed off while the call raised a deal-status
               flag nobody has answered. Allowing the close here made the flag
               disappear into a completed checklist, which is the one outcome a
               flag exists to prevent. */
            $blocked = $task->blockedReason();

            if ($blocked !== null) {
                return back()->with('error', $blocked);
            }
        }

        $task->update(['status' => $task->status === 'closed' ? 'todo' : 'closed']);

        return back()->with('toast', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $this->authorizeDeally('deally.tasks.manage');
        $this->authorizeSeatRecord($task);

        /* Nothing is deleted. Archiving closes the task the same way any other
           close does, which also keeps the guard: a review task holding an
           unanswered deal-status flag cannot be archived through here. */
        if ($task->status !== 'closed') {
            $blocked = $task->blockedReason();

            if ($blocked !== null) {
                return back()->with('error', $blocked);
            }
        }

        $task->update(['status' => 'closed']);

        app(ActivityLogger::class)->log(
            'task.archived',
            "Task '{$task->title}' archived."
        );

        return back()->with('toast', 'Task archived.');
    }

    /**
     * Rule shared by every task-creation surface: a due moment is required and
     * a calendar date on its own is not enough.
     *
     * @return (Closure(string, mixed, Closure(string):void):void)|string
     */
    protected function requiresDueTime(): Closure|string
    {
        return function ($attribute, $value, $fail): void {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $fail('A due time is required — a date on its own is not enough.');
            }
        };
    }
}
