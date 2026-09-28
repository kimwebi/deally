@extends('core::layouts.app', [
    'pageTitle' => 'Tasks',
    'pageSub' => $tasks->count().' total · '.$overdueCount.' overdue',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Tasks</div>
            <div class="list-subtitle">{{ $tasks->count() }} total · {{ $overdueCount }} overdue</div>
        </div>
        @if (! $tasks->isEmpty())
            <span class="btn-sm primary" data-open-modal="modal-add-task" style="cursor: pointer;">＋ New Task</span>
        @endif
    </div>

    <table class="data-table">
        <thead>
            <tr><th>Task</th><th>Linked To</th><th>Due</th><th>Status</th><th style="text-align:right;"></th></tr>
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
                    <td>{{ $task->linked_company ?: '—' }}</td>
                    <td class="mono" @if ($isOverdue) style="color: var(--red);" @endif>
                        {{ $task->due_at ? $task->due_at->format('M d, g:ia') : '—' }}{{ $isOverdue ? ' · Overdue' : '' }}
                    </td>
                    <td>
                        <span class="status-pill {{ $statusPill }}">{{ $statusLabel }}</span>
                        @if ($blockedReason = $blockedTasks->get($task->id))
                            {{-- Blocked is shown on the row, not only inside the
                                 modal. Hiding it means a rep clicks ✓, gets a
                                 flash they may not read, and concludes the task
                                 is unclosable. --}}
                            <span class="status-pill blocked" title="{{ $blockedReason }}">⚑ Needs a decision</span>
                        @endif
                    </td>
                    <td style="text-align:right; white-space: nowrap;">
                        <button class="row-action"
                            data-open-modal="modal-task"
                            data-modal-url="{{ route('deally.tasks.show', $task) }}"
                            data-review-url="{{ $reviewUrls[$task->id] ?? '' }}">{{ $isClosed ? 'Reopen' : 'Review' }}</button>
                        <form method="POST" action="{{ route('deally.tasks.toggle', $task) }}" style="display: inline;">
                            @csrf
                            <button class="row-action" type="submit" @disabled($blockedReason !== null)
                                title="{{ $blockedReason ?? 'Close this task' }}">{{ $isClosed ? '↺' : '✓' }}</button>
                        </form>
                        <form method="POST" action="{{ route('deally.tasks.destroy', $task) }}" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button class="row-action" type="submit" onclick="return confirm('Delete this task?')">✕</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center; color: var(--text-3);">No tasks yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@push('modals')
{{-- One shell; the review task body is fetched per task. --}}
<div class="modal-overlay" id="modal-task">
    <div id="modal-task-host" data-modal-host></div>
</div>

<div class="modal-overlay" id="modal-add-task">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon task">📋</div>
            <div class="modal-header-body"><div class="modal-title">New Task</div><div class="modal-subtitle">Create a follow-up</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.tasks.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Title</div>
                    <input class="input-field" name="title" placeholder="e.g. Follow up with Stark Industries" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Assignee</div>
                    <select class="input-field" name="assignee_user_id">
                        <option value="">Me — {{ auth()->user()->name }}</option>
                        @php
                            $suggestedForAssignment = $suggestedOwners ?? collect();
                            $remainingAssignees = $assignees->reject(fn ($name, $id) => $suggestedForAssignment->has($id));
                        @endphp
                        @if ($suggestedForAssignment->isNotEmpty())
                            <optgroup label="Suggested · your team">
                                @foreach ($suggestedForAssignment as $assigneeId => $assigneeName)
                                    @if ($assigneeId !== auth()->id())
                                        <option value="{{ $assigneeId }}">{{ $assigneeName }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($remainingAssignees->isNotEmpty())
                            <optgroup label="Everyone else">
                                @foreach ($remainingAssignees as $assigneeId => $assigneeName)
                                    @if ($assigneeId !== auth()->id())
                                        <option value="{{ $assigneeId }}">{{ $assigneeName }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="field-block">
                    <div class="field-label">Linked to</div>
                    <input class="input-field" name="linked_company" placeholder="Acme Corp">
                </div>
                <div class="field-block">
                    <div class="field-label">Due date &amp; time</div>
                    <input class="input-field" type="datetime-local" name="due_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Create Task</button>
            </div>
        </form>
    </div>
</div>
@endpush