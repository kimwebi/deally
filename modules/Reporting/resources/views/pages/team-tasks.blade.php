@extends('core::layouts.app', [
    'pageTitle' => 'Team Task Overview',
    'pageSub' => $tasks->count().' tasks'.($activeAssignee ? ' · '.$activeAssignee : ' · all assignees'),
])

@section('content')
<div class="list-page">
    <div class="report-hero">
        <div>
            <div class="report-hero-title">Team Task Overview</div>
            <div class="report-hero-sub">Every task across the team with assignee, linked account, and due time.</div>
        </div>
        <div class="report-hero-actions">
            <a href="{{ route('deally.reporting') }}" class="btn-sm">Dashboard</a>
            <a href="{{ route('deally.tasks.index') }}" class="btn-sm primary">My Tasks</a>
        </div>
    </div>

    <div class="list-toolbar">
        <a href="{{ route('deally.reporting.tasks') }}" class="filter-chip {{ $activeAssignee ? '' : 'active' }}">All</a>
        @foreach ($assignees as $assignee)
            <a href="{{ route('deally.reporting.tasks', ['assignee' => $assignee]) }}" class="filter-chip {{ $activeAssignee === $assignee ? 'active' : '' }}">{{ $assignee }}</a>
        @endforeach
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Task</th><th>Assignee</th><th>Linked To</th><th>Due</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse ($tasks as $task)
                @php
                    $isClosed = $task->status === 'closed';
                    $isOverdue = ! $isClosed && $task->due_at !== null && $task->due_at->isPast();
                    $statusLabel = $isClosed ? 'Closed' : ($isOverdue ? 'Overdue' : 'To Do');
                    $statusPill = $isClosed ? 'closed' : ($isOverdue ? 'rejected' : 'pending');
                @endphp
                <tr>
                    <td class="primary">{{ $task->title }}</td>
                    <td>{{ $task->assignee ?: 'Unassigned' }}</td>
                    <td>{{ $task->linked_company ?: '—' }}</td>
                    <td class="mono" @if ($isOverdue) style="color: var(--red);" @endif>
                        {{ $task->due_at ? $task->due_at->format('M d, g:ia') : '—' }}{{ $isOverdue ? ' · Overdue' : '' }}
                    </td>
                    <td><span class="status-pill {{ $statusPill }}">{{ $statusLabel }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No tasks match this view.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection