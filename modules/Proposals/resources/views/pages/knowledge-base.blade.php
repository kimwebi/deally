@extends('core::layouts.app', [
    'pageTitle' => 'Knowledge Base',
    'pageSub' => $entries->count().' entries · '.$gaps->count().' gap logs pending',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Knowledge Base</div>
            <div class="list-subtitle">{{ $entries->count() }} entries · {{ $gaps->count() }} gap logs pending</div>
        </div>
        @if (! ($entries->isEmpty() && $gaps->isEmpty()))
            <span class="btn-sm primary" data-open-modal="modal-addkb" style="cursor: pointer;">＋ Add KB</span>
        @endif
    </div>

    <div class="list-toolbar">
        <button class="filter-chip active" data-kb-tab="entries">KB Entries</button>
        <button class="filter-chip" data-kb-tab="gaps">Knowledge Gap Logs <span style="margin-left: 6px; color: var(--amber);">{{ $gaps->count() }}</span></button>
    </div>

    <div id="kb-tab-entries">
        <div class="kb-grid">
            @forelse ($entries as $entry)
                <div class="kb-card">
                    <div class="kb-card-type">{{ ucfirst($entry->type) }}</div>
                    <div class="kb-card-title">{{ $entry->title }}</div>
                    <div class="kb-card-sub">{{ $entry->description }}</div>
                </div>
            @empty
                <div style="grid-column: 1 / -1; font-size: 12px; color: var(--text-3); text-align: center; padding: 30px 0;">No knowledge entries yet.</div>
            @endforelse
        </div>
    </div>

    <div id="kb-tab-gaps" style="display: none; margin-top: 10px;">
        @forelse ($gaps as $gap)
            <div class="gap-log-row">
                <div class="gap-log-icon {{ str_contains($gap->type, 'competitor') ? 'competitor' : ($gap->type === 'correction' ? 'correction' : 'gap') }}">
                    {{ str_contains($gap->type, 'competitor') ? '🔵' : ($gap->type === 'correction' ? '✎' : '❓') }}
                </div>
                <div class="gap-log-body">
                    <div class="gap-log-title">"{{ $gap->text }}"</div>
                    <div class="gap-log-meta">{{ $gap->source }} · {{ ucfirst($gap->status) }}</div>
                </div>
                <div><span class="status-pill {{ $gap->status === 'pending' ? 'pending' : 'live' }}">{{ ucfirst($gap->status) }}</span></div>
            </div>
        @empty
            <div style="font-size: 12px; color: var(--text-3); text-align: center; padding: 20px 0;">No knowledge gaps flagged.</div>
        @endforelse
    </div>
</div>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-addkb">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon kb">📚</div>
            <div class="modal-header-body"><div class="modal-title">Add Knowledge Base Entry</div><div class="modal-subtitle">Choose a template</div></div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <form method="POST" action="{{ route('deally.kb.store') }}">
            @csrf
            <div class="modal-body">
                <div class="field-block">
                    <div class="field-label">Type</div>
                    <select class="input-field" name="type">
                        @foreach (\Deally\Proposals\Models\KnowledgeEntry::types() as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-block">
                    <div class="field-label">Title</div>
                    <input class="input-field" name="title" placeholder="Enterprise Suite" required>
                </div>
                <div class="field-block">
                    <div class="field-label">Description</div>
                    <textarea class="input-field" name="description" placeholder="500+ users · SSO · Slack integration"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-sm" type="button" data-close-modal>Cancel</button>
                <button class="btn-sm primary" type="submit">Save Entry</button>
            </div>
        </form>
    </div>
</div>
@endpush