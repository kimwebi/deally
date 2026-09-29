@extends('core::layouts.app', [
    'pageTitle' => 'Knowledge Base',
    'pageSub' => $entries->total().' entries · '.$gaps->count().' gap logs pending',
])

@section('content')
<div class="list-page">
    <div class="list-header">
        <div>
            <div class="list-title">Knowledge Base</div>
            <div class="list-subtitle">{{ $entries->total() }} entries · {{ $gaps->count() }} gap logs pending</div>
        </div>
        @if (! ($entries->total() === 0 && $gaps->isEmpty()))
            <span class="btn-sm primary" data-open-modal="modal-addkb" style="cursor: pointer;">＋ Add KB</span>
        @endif
    </div>

    <div class="list-toolbar">
        <button class="filter-chip active" data-kb-tab="entries">KB Entries</button>
        <button class="filter-chip" data-kb-tab="gaps">Knowledge Gap Logs <span style="margin-left: 6px; color: var(--amber);">{{ $gaps->count() }}</span></button>

        <form class="kb-search" method="GET" action="{{ route('deally.kb.index') }}">
            <i class="bi bi-search"></i>
            <input type="search" name="search" id="kb-search-input" value="{{ $search }}" placeholder="Search words, phrases…" autocomplete="off" aria-label="Search knowledge base">
            @if ($search !== '')
                <a class="kb-search-clear" href="{{ route('deally.kb.index') }}" title="Clear search">&times;</a>
            @endif
        </form>
    </div>

    <div id="kb-tab-entries">
        <div class="kb-grid">
            @forelse ($entries as $entry)
                <div class="kb-card"
                     data-open-modal="modal-viewkb"
                     data-title="{{ $entry->title }}"
                     data-desc="{{ $entry->description ?: 'No description provided.' }}"
                     data-kbsub="{{ ucfirst($entry->type) }} · entry #{{ $entry->id }}">
                    <div class="kb-card-type">{{ ucfirst($entry->type) }}</div>
                    <div class="kb-card-title">{{ $entry->title }}</div>
                    <div class="kb-card-sub">{{ $entry->description }}</div>
                    <div class="kb-card-view"><i class="bi bi-arrow-up-right"></i> View</div>
                </div>
            @empty
                <div class="kb-empty" style="grid-column: 1 / -1; font-size: 12px; color: var(--text-3); text-align: center; padding: 30px 0;">
                    {{ $search !== '' ? 'No entries match "'.$search.'".' : 'No knowledge entries yet.' }}
                </div>
            @endforelse
        </div>

        @if ($entries->hasPages())
            <div class="kb-pagination">
                <a class="page-btn {{ $entries->onFirstPage() ? 'disabled' : '' }}" href="{{ $entries->previousPageUrl() ?? '#' }}">‹ Prev</a>
                <span class="page-info">{{ $entries->firstItem() }}–{{ $entries->lastItem() }} of {{ $entries->total() }}</span>
                @for ($page = 1; $page <= $entries->lastPage(); $page++)
                    <a class="page-num {{ $page === $entries->currentPage() ? 'active' : '' }}" href="{{ $entries->url($page) }}">{{ $page }}</a>
                @endfor
                <a class="page-btn {{ $entries->hasMorePages() ? '' : 'disabled' }}" href="{{ $entries->nextPageUrl() ?? '#' }}">Next ›</a>
            </div>
        @endif
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

<script>
(function () {
    var input = document.getElementById('kb-search-input');
    if (!input || !input.form) return;
    var timer = null;
    input.addEventListener('input', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { input.form.submit(); }, 350);
    });
})();
</script>
@endsection

@push('modals')
<div class="modal-overlay" id="modal-viewkb">
    <div class="modal wide">
        <div class="modal-header">
            <div class="modal-header-icon kb"><i class="bi bi-node-plus-fill"></i></div>
            <div class="modal-header-body">
                <div class="modal-title" data-fill="title"></div>
                <div class="modal-subtitle" data-fill="kbsub"></div>
            </div>
            <button class="modal-close" data-close-modal>✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-section-label"><span class="dot"></span>Answer in the Knowledge Base</div>
            <p class="kb-view-desc" data-fill="desc"></p>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-addkb">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-icon kb"><i class="bi bi-node-plus-fill"></i></div>
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