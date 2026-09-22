<div class="modal-overlay" id="modal-task">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon task">📋</div>
            <div class="modal-header-body"><div class="modal-title">New Task</div><div class="modal-subtitle">Time block · Today</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.tasks.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Title</div>
                    <input class="input-field" name="title" placeholder="Prep notes for the Acme call" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Assignee</div>
                    <select class="input-field" name="assignee_user_id">
                        <option value="">Me — {{ auth()->user()->name }}</option>
                        @foreach ($assignees ?? [] as $assigneeId => $assigneeName)
                            @if ($assigneeId !== auth()->id())
                                <option value="{{ $assigneeId }}">{{ $assigneeName }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="field-block">
                    <div class="field-label">Linked to</div>
                    <input class="input-field" name="linked_company" placeholder="Acme Corp">
                </div>
                <div class="field-block">
                    <div class="field-label">Due date &amp; time</div>
                    <input class="input-field" type="datetime-local" name="due_at" value="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Create Task</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-event">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon call">⚡</div>
            <div class="modal-header-body"><div class="modal-title">New Event</div><div class="modal-subtitle">Schedule onto your day</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.workspace.event') }}">
            @csrf
            <input type="hidden" name="type" data-event-type value="deally">
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Event Type</div>
                    <div class="type-picker">
                        <div class="type-option selected" data-type="deally"><span class="to-icon">⚡</span><div class="to-label">DeAlly Call</div></div>
                        <div class="type-option" data-type="external"><span class="to-icon">📅</span><div class="to-label">External</div></div>
                        <div class="type-option" data-type="task"><span class="to-icon">📋</span><div class="to-label">Task Block</div></div>
                    </div>
                </div>
                <div class="field-block">
                    <div class="field-label">Title</div>
                    <input class="input-field" name="title" placeholder="e.g., Discovery call with Acme" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Customer / Contact</div>
                    <input class="input-field" name="linked_company" placeholder="Search customers…">
                </div>
                <div class="field-block">
                    <div class="field-label">Due date &amp; time</div>
                    <input class="input-field" type="datetime-local" name="due_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Create Event</button>
            </div>
        </form>
    </div>
</div>