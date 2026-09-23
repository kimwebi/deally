@extends('core::layouts.app', [
    'pageTitle' => 'Solutions Lead',
    'pageSub' => 'Quality gate for AI answers · '.$gapsPendingCount.' gaps in queue',
])

@section('content')
<div class="sl-workspace">
    <aside class="sl-pane">
        <div class="sl-card">
            <div class="sl-card-head">KB health</div>
            <div class="kpi-grid">
                <div class="kpi">
                    <div class="kpi-num">{{ $entriesCount }}</div>
                    <div class="kpi-label">Live entries</div>
                </div>
                <div class="kpi">
                    <div class="kpi-num amber">{{ $gapsPendingCount }}</div>
                    <div class="kpi-label">Gaps pending</div>
                </div>
            </div>
        </div>

        <div class="sl-card">
            <div class="sl-card-head">This week</div>
            <div class="sl-stat-row"><span>Gaps flagged</span><strong>{{ $gapsThisWeek }}</strong></div>
            <div class="sl-stat-row"><span>Corrected</span><strong>{{ $corrections->count() }}</strong></div>
            <div class="sl-stat-row"><span>Expert pings</span><strong>{{ $expertPings->count() }}</strong></div>
        </div>

        <div class="sl-card">
            <div class="sl-card-head">Voice of customer</div>
            @if (count($voc['rows']) > 0)
                <div class="heatmap">
                    <div class="heatmap-row heatmap-months">
                        <span class="heatmap-tag"></span>
                        <div class="heatmap-cells">
                            @foreach ($voc['months'] as $month)
                                <span class="heatmap-month">{{ $month }}</span>
                            @endforeach
                        </div>
                    </div>
                    @foreach ($voc['rows'] as $row)
                        <div class="heatmap-row">
                            <span class="heatmap-tag" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $row['label'] }}</span>
                            <div class="heatmap-cells">
                                @foreach ($row['cells'] as $cell)
                                    <span class="heatmap-cell {{ $cell }}" title="{{ $row['label'] }}"></span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="sl-row-note">No call signal yet — VOC trends appear as calls are logged.</div>
            @endif
            <div style="font-size:11px;color:var(--text-3);margin-top:10px;">
                @if ($voc['total30'] > 0)
                    Last 30 days: {{ $voc['positive30'] }} positive · {{ $voc['negative30'] }} negative across {{ $voc['total30'] }} calls.
                    @if ($voc['negative30'] > 0)
                        <span style="color:var(--danger-bright);font-weight:500;"> {{ $voc['negative30'] }} need attention.</span>
                    @endif
                @else
                    No calls logged in the last 30 days.
                @endif
            </div>
        </div>
    </aside>

    <section class="sl-pane">
        <div class="sl-pane-head">
            <div>
                <div class="sl-pane-title">Gap Queue</div>
                <div class="sl-pane-sub">Approve answers into the KB, edit them, or reject.</div>
            </div>
            <span class="sl-count-pill">{{ $gapsPendingCount }}</span>
        </div>

        <div class="sl-gap-list">
            @forelse ($pendingGaps as $gap)
                <div class="sl-gap-item">
                    <div class="sl-gap-eyebrow">Gap log · {{ strtoupper(str_replace('-', ' ', $gap->type)) }}</div>
                    <div class="sl-gap-q">"{{ $gap->text }}"</div>
                    <div class="sl-gap-meta">{{ $gap->source }} · {{ $gap->created_at->diffForHumans() }}</div>
                    <form method="POST" action="{{ route('deally.solutions.gaps.resolve', $gap) }}" class="sl-gap-actions">
                        @csrf
                        <button class="sl-btn yes" name="action" value="approve">✓ Approve</button>
                        <button class="sl-btn edit" type="button" data-edit-target="sl-edit-{{ $gap->id }}">✎ Edit</button>
                        <button class="sl-btn no" name="action" value="reject">✕ Reject</button>
                        <div class="sl-gap-edit" id="sl-edit-{{ $gap->id }}" hidden>
                            <textarea name="text" class="sl-edit-input">{{ $gap->text }}</textarea>
                            <button class="sl-btn yes" name="action" value="edit">Apply Edit</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="sl-empty-card">Queue clear — no gaps waiting to be resolved.</div>
            @endforelse
        </div>
    </section>

    <aside class="sl-pane">
        <div class="sl-card">
            <div class="sl-card-head">Expert pings</div>
            <div class="sl-row-list">
                @forelse ($expertPings as $ping)
                    <div class="sl-row">
                        <div class="sl-row-title">{{ $ping->text }}</div>
                        <div class="sl-row-meta">{{ $ping->source }} · awaiting reply</div>
                    </div>
                @empty
                    <div class="sl-row-note">No expert pings waiting.</div>
                @endforelse
            </div>
        </div>

        <div class="sl-card">
            <div class="sl-card-head">Recent corrections</div>
            <div class="sl-row-list">
                @forelse ($corrections as $correction)
                    <div class="sl-row">
                        <div class="sl-row-title">{{ $correction->text }}</div>
                        <div class="sl-row-meta">{{ $correction->source }} · logged {{ $correction->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="sl-row-note">No corrections logged yet.</div>
                @endforelse
            </div>
        </div>
    </aside>
</div>

<script>
document.querySelectorAll('[data-edit-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var target = document.getElementById(btn.getAttribute('data-edit-target'));
        if (target) target.hidden = !target.hidden;
    });
});
</script>
@endsection
