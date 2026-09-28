
@php
    use Deally\Calls\Models\CallFinding;

    $archived = app(\Deally\Retention\Services\RetentionService::class)->isTranscriptArchived($call);
    $readiness = $call->effectiveReadiness();
    $findings = $call->findings()->orderBy('id')->get();
@endphp

<div class="modal" id="call-detail-modal-body">
    <div class="modal-header">
        <div class="modal-header-icon call"><i class="bi bi-telephone-fill" style="color: #ff0062"></i></div>
        <div class="modal-header-body">
            <div class="modal-title">{{ $call->name }}</div>
            <div class="modal-subtitle">
                {{ $call->company }} · {{ $call->date->format('j M Y') }}
                @if ($call->contact_name)
                    · {{ $call->contact_name }}{{ $call->contact_role ? ' ('.$call->contact_role.')' : '' }}
                @endif
            </div>
        </div>
        <button class="modal-close" data-close-modal>✕</button>
    </div>

    <div class="modal-body call-detail-body">
        @if ($archived)
            <div class="call-detail-archived"><i class="bi bi-lock-fill" style="color: #9f9f02"></i> This transcript is archived. Contact an admin to retrieve it.</div>
        @endif

        {{-- Outcome first. --}}
        <div class="call-detail-summary">
            <div class="call-detail-chips">
               <span class="status-pill {{ match ($readiness) { 'hot' => 'live', 'cold' => 'rejected', default => 'pending' } }}">
                   {!! match ($readiness) { 'hot' => '<i class="bi bi-fire"></i> Hot', 'cold' => '<i class="bi bi-snow"></i> Cold', default => '<i class="bi bi-sun"></i> Warm' } !!} — close readiness
               </span>

                <span class="status-pill pending">{{ ucfirst($call->effectiveSentiment()) }} sentiment</span>
                <span class="status-pill pending">{{ $call->duration ?: '0m' }}</span>
                @if ($call->proposal_intent)
                    <span class="status-pill live">Proposal agreed</span>
                @endif
            </div>

            <p class="call-detail-summary-text">
                {{ $call->summary ?: 'No summary was written for this call.' }}
            </p>

            {{-- The AI's original read stays visible beside the corrected one.
                 Overwriting it would make it impossible to tell whether the
                 review improved the record or merely changed it. --}}
            @if ($call->sentimentWasCorrected() || $call->readinessWasCorrected())
                <div class="call-detail-correction">
                    <span class="call-detail-correction-label">Corrected after the call</span>
                    @if ($call->sentimentWasCorrected())
                        <span>AI read sentiment <em>{{ $call->ai_sentiment }}</em>, rep set it to <em>{{ $call->sentiment }}</em>.</span>
                    @endif
                    @if ($call->readinessWasCorrected())
                        <span>AI read readiness <em>{{ $call->ai_readiness }}</em>, rep set it to <em>{{ $call->readiness }}</em>.</span>
                    @endif
                </div>
            @endif
        </div>

        @if ($call->openFlags->isNotEmpty())
            <div class="call-detail-flags">
                <div class="rail-subhead">Deal status</div>
                @foreach ($call->openFlags as $openFlag)
                    <div class="call-detail-flag">
                        <strong>{{ $openFlag->headline }}</strong>
                        @if ($openFlag->rationale)
                            <div>{{ $openFlag->rationale }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="call-detail-section">
            <div class="call-detail-section-title">
                What DeAlly picked up
                <span class="call-detail-count">{{ $findings->count() }}</span>
            </div>

            @forelse ($findings as $finding)
                <div class="call-detail-finding">
                    <div class="call-detail-finding-head">
                        <span class="call-detail-finding-label">{{ $finding->label ?: ucfirst(str_replace('_', ' ', $finding->kind)) }}</span>
                        @if ($finding->status === CallFinding::STATUS_UNHELPFUL)
                            <span class="call-detail-flag-unhelpful">Marked not useful</span>
                        @endif
                    </div>
                    <div class="call-detail-finding-body">{{ $finding->body }}</div>
                    @if ($finding->package)
                        <div class="call-detail-finding-foot">{{ $finding->package }}</div>
                    @endif
                </div>
            @empty
                <p class="call-detail-empty">
                    No findings were recorded for this call. That is what an empty shelf means when no provider
                    failure was reported — see <code>analysis_failed</code> on the live session.
                </p>
            @endforelse
        </div>

        <div class="call-detail-section">
            <div class="call-detail-section-title">
                Transcript
                <span class="call-detail-count">{{ $call->transcriptLines->count() }}</span>
            </div>

            <div class="call-detail-transcript">
                @forelse ($call->transcriptLines->take(40) as $line)
                    <div class="transcript-line">
                        <div class="speaker {{ $line->is_agent ? 'agent' : '' }}">{{ $line->is_agent ? 'Agent' : $line->speaker }}</div>
                        <div class="transcript-text">{{ $line->text }}</div>
                    </div>
                @empty
                    <p class="call-detail-empty">No transcript was captured for this call.</p>
                @endforelse
                @if ($call->transcriptLines->count() > 40)
                    <p class="call-detail-empty">Showing the first 40 lines. The full conversation is in the review.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn-sm" type="button" data-close-modal>Close</button>
        <a href="{{ route('deally.calls.summary', $call) }}" class="btn-sm">Summary</a>
        <a href="{{ route('deally.calls.review', $call) }}" class="btn-sm primary" data-review-target>Open full review</a>
    </div>
</div>
