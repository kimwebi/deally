<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Lifecycle · DeAlly Docs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@200;300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="ambient"></div>

<div id="docs" class="screen active">
    <div class="docs-card">
        <a href="{{ route('login') }}" class="docs-back">← Sign in</a>

        <div class="login-logo"><div class="bolt"><i class="bi bi-lightning-fill"></i></div>DeAlly</div>
        <div class="login-sub">Call lifecycle — initiation, live sessions, replay &amp; review</div>

        <div style="display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap;">
            <a href="{{ route('docs.overview') }}" class="btn-sm {{ request()->routeIs('docs.overview') ? 'primary' : '' }}">Product Overview</a>
            <a href="{{ route('docs.calls') }}" class="btn-sm {{ request()->routeIs('docs.calls') ? 'primary' : '' }}">Call Lifecycle</a>
            <a href="{{ route('docs.ai') }}" class="btn-sm {{ request()->routeIs('docs.ai') ? 'primary' : '' }}">Live AI Assistant</a>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Core design principle: honest state</div>
            <p class="docs-text">
                A sales rep must never be told something that did not happen. If a meeting link does not exist,
                DeAlly never generates a dummy one. If an AI platform connector is not connected, the interface
                transparently indicates that rather than giving false assurance. Every state in DeAlly is backed by
                verifiable records:
            </p>
            <ul class="docs-list">
                <li><b>No fabricated join URLs</b> — <span class="font-mono">meeting_join_url</span> is only set when confirmed by an integrated platform provider.</li>
                <li><b>No invented bot joins</b> — the bot join status is marked <span class="font-mono">joined</span> only upon real provider confirmation.</li>
                <li><b>Verbatim invitation records</b> — invitations store the exact message text sent to the customer.</li>
                <li><b>Durable live ephemeral stream</b> — every ephemeral card is stored in the database before display, so post-call reviews reflect exactly what the AI observed.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">1. Scheduling &amp; meeting platforms</div>
            <p class="docs-text">
                When scheduling a call in the Calls module, agents choose a call name, company, contact, session type
                (e.g., Discovery, Demo, Service Review, Follow-up), date, meeting platform, customer email, and assignee.
            </p>
            <p class="docs-text">
                <b>Meeting platforms</b> (Zoom, Google Meet, Microsoft Teams, or DeAlly Live Session) display their
                active connection status. If credentials have not been configured for an external platform connector,
                it is clearly flagged as <i>Not connected</i> with the exact reason. The call can always proceed seamlessly
                via DeAlly's own live session.
            </p>
            <p class="docs-text">
                <b>Invitations</b> — if a customer email is provided, an invitation is generated with
                the scheduled details. The copy is stored permanently on the call record.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">2. Live call session &amp; the ephemeral stream</div>
            <p class="docs-text">
                During the call, audio is streamed in 4-second chunks across two independent channels: the agent's microphone
                and the customer/meeting audio. Transcripts and AI findings populate in near real time.
            </p>
            <p class="docs-text">
                Alongside persistent findings on the right shelf, an <b>ephemeral stream</b> displays real-time events:
            </p>
            <ul class="docs-list">
                <li><b>Heard</b> — a concise paraphrase (at most ~12 words) of what the customer communicated, preventing verbatim misquotes.</li>
                <li><b>Detected</b> — real-time detection of buying intent, competitor mentions, or risks.</li>
                <li><b>Gap</b> — questions or requirements not currently covered in the Knowledge Base.</li>
                <li><b>Asked</b> — ad-hoc queries the agent submitted to the AI assistant mid-call.</li>
                <li><b>Objection</b> — customer hesitations or pricing pushback.</li>
            </ul>
            <p class="docs-text">
                Cards use distinct visual edge treatments (solid for speech guidance, dashed for questions to ask, red left border for objections)
                rather than relying solely on color.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">3. Post-call summary &amp; review task</div>
            <p class="docs-text">
                When the call ends, audio buffers are flushed cleanly, the call status transitions to completed, and the
                rep is guided to the <b>Post-Call Summary</b>.
            </p>
            <p class="docs-text">
                <b>Proposal Intent Gating:</b> The "Create Proposal" action appears only if the AI detected genuine customer
                agreement on proposal terms during the call, eliminating premature proposals.
            </p>
            <p class="docs-text">
                <b>The Review Task:</b> A dedicated task is generated for the call. It opens directly in a focused modal from the
                Tasks page:
            </p>
            <ul class="docs-list">
                <li><b>Audio Replay</b> — interactive playback with seek bar and line-by-line <span class="font-mono">▶</span> jump buttons.</li>
                <li><b>Correctable Sentiment &amp; Readiness</b> — reps can correct AI sentiment or buyer readiness; the original AI assessment is preserved alongside the human correction for model evaluation.</li>
                <li><b>Deal-Status Flags</b> — risk flags raised on a deal require explicit resolution notes. <b>The review task cannot be closed while an open flag exists.</b></li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Calls module API endpoints</div>
            <table class="docs-table">
                <thead>
                    <tr><th>Method</th><th>URI</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls</span></td><td>Calls listing and scheduled sessions</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls</span></td><td>Schedule a new call and compose invitation</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/detail</span></td><td>Load call detail modal with transcript &amp; open flags</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/live</span></td><td>Live audio capture &amp; AI assistant screen</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/live/start</span></td><td>Initialize live session recording state</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/live/transcribe</span></td><td>Upload audio chunk for transcription &amp; analysis</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/live/query</span></td><td>Submit ad-hoc prompt to AI assistant</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/live/findings/{id}/feedback</span></td><td>Mark finding unhelpful (logs knowledge gap)</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/end</span></td><td>End call, drain upload queue, compute summary</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/summary</span></td><td>Post-call summary page</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/review</span></td><td>Full review page with audio replay</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/corrections</span></td><td>Submit correction (sentiment, readiness, competitor)</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/flags</span></td><td>Create deal-status risk flag</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/flags/{flag}/resolve</span></td><td>Resolve deal-status flag with resolution note</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/objections</span></td><td>Record customer objection</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/proposal</span></td><td>Create draft proposal from agreed intent</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/invitations</span></td><td>Send customer email invitation</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/invitations/preview</span></td><td>Preview invitation text</td></tr>
                    <tr><td><span class="code-method">POST</span></td><td><span class="font-mono">/app/calls/{call}/fail</span></td><td>Record failed or no-show call with optional reschedule</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/recordings/{recording}</span></td><td>Stream recorded audio segment for replay</td></tr>
                    <tr><td><span class="code-method">GET</span></td><td><span class="font-mono">/app/calls/{call}/transcript/download</span></td><td>Download complete formatted transcript</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="system-footer">
        <span class="system-footer-copy">© {{ date('Y') }} DeAlly · AI-powered sales enablement · Wyzone Labs</span>
    </footer>
</div>

</body>
</html>
