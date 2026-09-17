<?php

namespace Deally\Tasks\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Tasks\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::orderBy('created_at', 'desc')->get();

        $groups = [
            'todo' => $tasks->where('status', '!=', 'closed')->where('due_at', '>=', now()->startOfDay()),
            'overdue' => $tasks->where('status', '!=', 'closed')->whereNull('due_at')->concat($tasks->where('status', '!=', 'closed')->where('due_at', '<', now()->startOfDay())),
            'closed' => $tasks->where('status', 'closed'),
        ];

        return view('tasks::pages.tasks', [
            'tasks' => $tasks,
            'todoCount' => $tasks->where('status', '!=', 'closed')->count(),
            'overdueCount' => $tasks->where('status', '!=', 'closed')->filter(fn (Task $task) => $task->due_at !== null && $task->due_at->isPast())->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'linked_company' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        Task::create($data + ['status' => 'todo']);

        return back()->with('toast', 'Task created.');
    }

    public function toggle(Task $task)
    {
        $task->update(['status' => $task->status === 'closed' ? 'todo' : 'closed']);

        return back()->with('toast', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('toast', 'Task deleted.');
    }
}
