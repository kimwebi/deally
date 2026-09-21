<?php

namespace Deally\Tasks\Http\Controllers;

use Deally\Calls\Models\Call;
use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Services\ActivityLogger;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;

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

        $reviewCalls = $this->scopeToSeat(Call::query())
            ->whereIn('company', $tasks->pluck('linked_company')->filter()->unique())
            ->orderByDesc('created_at')
            ->get()
            ->unique('company');

        $reviewUrls = $reviewCalls->mapWithKeys(fn (Call $call) => [$call->company => route('deally.calls.review', $call)]);

        return view('tasks::pages.tasks', [
            'tasks' => $tasks,
            'reviewUrls' => $reviewUrls,
            'todoCount' => $tasks->where('status', '!=', 'closed')->count(),
            'overdueCount' => $tasks->where('status', '!=', 'closed')->filter(fn (Task $task) => $task->due_at !== null && $task->due_at->isPast())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeDeally('deally.tasks.manage');

        $data = $request->validate([
            'title' => ['required', 'string'],
            'assignee' => ['nullable', 'string'],
            'linked_company' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        Task::create($data + ['status' => 'todo', 'owner_user_id' => auth()->id()]);

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

        $task->update(['status' => $task->status === 'closed' ? 'todo' : 'closed']);

        return back()->with('toast', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $this->authorizeDeally('deally.tasks.manage');
        $this->authorizeSeatRecord($task);

        $task->delete();

        return back()->with('toast', 'Task deleted.');
    }
}
