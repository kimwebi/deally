<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live AI assistant · DeAlly Docs</title>
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
        <div class="login-sub">Live AI assistant — configuration guide</div>

        <div style="display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap;">
            <a href="{{ route('docs.overview') }}" class="btn-sm {{ request()->routeIs('docs.overview') ? 'primary' : '' }}">Product Overview</a>
            <a href="{{ route('docs.calls') }}" class="btn-sm {{ request()->routeIs('docs.calls') ? 'primary' : '' }}">Call Lifecycle</a>
            <a href="{{ route('docs.ai') }}" class="btn-sm {{ request()->routeIs('docs.ai') ? 'primary' : '' }}">Live AI Assistant</a>
        </div>

        <div class="docs-section">
            <div class="docs-heading">How it works</div>
            <p class="docs-text">
                During a live call, audio chunks from your mic (and the shared meeting audio, when you
                accept the screen-share prompt) are sent to the server. Each chunk is transcribed by the
                configured provider — Groq Whisper by default, OpenAI as an alternative — matched against
                the tenant knowledge base, and the suggested replies land on the Findings panel in near
                real time.
            </p>
            <p class="docs-text">
                Audio is captured in 4-second windows, and every window is transcribed. Filler that
                speech-to-text models invent for audio with no speech is discarded server-side, so the
                transcript only holds what was actually said. Only genuinely silent windows are skipped;
                if you speak quietly, your words are still transcribed. If a source stops being loud enough
                to transcribe, the page tells you to check the microphone or the meeting share.
            </p>
            <p class="docs-text">
                If a source stops delivering audio — an ended screen share, a muted microphone — the page
                reconnects it on its own and tells you what it did. You never need to reload mid-call to
                get transcription working again.
            </p>
            <p class="docs-text">
                Every card is written to be acted on: a finding names what changed and the next step, and its
                footer either points at a knowledge base entry to open or states
                <span class="font-mono">no KB entry — commit to a follow-up</span>. Each pass is told what it
                already reported, so the shelf shows what is new.
            </p>
            <p class="docs-text">
                General questions are answered from the model's own knowledge — geography, industry norms,
                definitions, how a product category works — because a visible non-answer costs you credibility
                with a customer who can already tell the question is answerable. Anything about
                <strong>our commercial terms</strong> is different: prices, discounts, contract wording, seat
                limits, SLAs, timelines, and certifications are never guessed. A model answer is labelled
                <span class="font-mono">answered from model knowledge — verify before quoting</span>, so you
                know there is no document behind it.
            </p>
            <p class="docs-text">
                Findings appear a few seconds after anything is said, whichever side said it — including your
                own microphone, so you can try the panel out by speaking the customer's part. Analysis used to
                run on customer lines only, on the reasoning that advising you on your own words is noise. In
                practice that made a rep testing on their own look like a provider that had stopped working:
                a healthy transcript, an always-empty shelf. The judgement now lives in the prompt, not in a
                hard gate.
            </p>
            <p class="docs-text">
                The source label counts what went missing rather than hiding it.
                <span class="font-mono">● Live · 2 windows lost</span> means audio never reached the
                provider — check the microphone or the meeting share.
                <span class="font-mono">AI unavailable</span> means transcription is fine but the suggestions
                could not be generated for that window; the call is still recorded and the reason is logged.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">The stream beside the findings</div>
            <p class="docs-text">
                Short-lived cards run down the middle of the call screen: what was just said, what just
                got detected, a question the knowledge base cannot answer, something you asked, an
                objection. The panel on the right is for things that still matter; the stream is for
                things that just happened, said once and gone.
            </p>
            <p class="docs-text">
                <b>Every card is saved before it is shown.</b> The card is written to the call's record
                and only then sent to the browser, so the six-second fade is a rule about visibility and
                nothing else. The review task and the review page read those rows back — which is the
                only reason the review can tell you what the assistant was attending to instead of
                leaving you to reconstruct it from a scrolling panel.
            </p>
            <p class="docs-text">
                <b>Clearing the findings only clears the screen.</b> It empties the panel; it does not
                delete anything.
            </p>
            <p class="docs-text">
                <b>The "Heard" card is a summary, not a quote.</b> It is the model's own paraphrase of
                what the customer just said — at most about a dozen words, in the present tense, and
                never wrapped in quotation marks. A card labelled as a summary that is actually the
                customer's exact sentence is worse than no card, because you read it as their words.
                Nothing is ever filled in locally: if the model has nothing to add, no card appears.
            </p>
            <p class="docs-text">
                Cards are told apart by their <b>edge</b>, not their colour — a solid edge means "say
                this", a dashed edge "ask this", no accent a reference, a dotted edge something waiting,
                and a red rule an objection. A colour alone excludes anyone with a colour vision
                deficiency, and a screenshot in a handover has to survive being printed in black and
                white.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Driver selection</div>
            <p class="docs-text">The server picks the driver from <span class="font-mono">LIVE_AI_DRIVER</span> at runtime:</p>
            <ul class="docs-list">
                <li><span class="font-mono">auto</span> (default) prefers <strong>Groq</strong> when <span class="font-mono">GROQ_API_KEY</span> is set, then <strong>OpenAI</strong> when <span class="font-mono">OPENAI_API_KEY</span> is set.</li>
                <li><span class="font-mono">groq</span> or <span class="font-mono">openai</span> pins a provider; <span class="font-mono">dummy</span> runs the simulated demo with no provider requests.</li>
                <li>Outside production, <span class="font-mono">auto</span> falls back to the simulated driver when no key is set — the live UI still works for demos.</li>
                <li>Both providers speak OpenAI-compatible endpoints, so only server-side config differs.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Configuration (<span class="font-mono">.env</span>)</div>
            <table class="docs-table">
                <thead>
                    <tr><th>Variable</th><th>Default</th><th>Purpose</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="font-mono">LIVE_AI_DRIVER</span></td><td><span class="font-mono">auto</span></td><td>auto · groq · openai · dummy</td></tr>
                    <tr><td><span class="font-mono">GROQ_API_KEY</span></td><td>—</td><td>Enables the Groq driver.</td></tr>
                    <tr><td><span class="font-mono">GROQ_TRANSCRIPTION_MODEL</span></td><td><span class="font-mono">whisper-large-v3-turbo</span></td><td>Audio transcription.</td></tr>
                    <tr><td><span class="font-mono">GROQ_CHAT_MODEL</span></td><td><span class="font-mono">openai/gpt-oss-20b</span></td><td>Suggested replies &amp; answers.</td></tr>
                    <tr><td><span class="font-mono">GROQ_TIMEOUT</span></td><td><span class="font-mono">45</span></td><td>Request timeout (seconds).</td></tr>
                    <tr><td><span class="font-mono">GROQ_CA_BUNDLE</span></td><td>—</td><td>Path to a CA bundle used to verify Groq's certificate.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_CA_BUNDLE</span></td><td>—</td><td>Same, for the OpenAI driver.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_API_KEY</span></td><td>—</td><td>Enables the OpenAI driver.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_TRANSCRIPTION_MODEL</span></td><td><span class="font-mono">whisper-1</span></td><td>Audio transcription.</td></tr>
                    <tr><td><span class="font-mono">OPENAI_CHAT_MODEL</span></td><td><span class="font-mono">gpt-4o-mini</span></td><td>Suggested replies &amp; answers.</td></tr>
                    <tr><td><span class="font-mono">LIVE_AI_ANALYSIS_WINDOW</span></td><td><span class="font-mono">8</span></td><td>Transcript lines sent for analysis.</td></tr>
                    <tr><td><span class="font-mono">LIVE_AI_ANALYSIS_MIN_INTERVAL</span></td><td><span class="font-mono">10</span></td><td>Minimum seconds between analysis runs.</td></tr>
                    <tr><td><span class="font-mono">LIVE_AI_SHELF_LIMIT</span></td><td><span class="font-mono">10</span></td><td>Newest findings rendered in the shelf; older ones stay saved.</td></tr>
                    <tr><td><span class="font-mono">LIVE_AI_REPORTED_FINDINGS</span></td><td><span class="font-mono">6</span></td><td>Already-reported cards shown to the model so each pass reports what is new.</td></tr>
                </tbody>
            </table>
            <p class="docs-text" style="margin-top:10px;">After changing these, run <span class="font-mono">php artisan config:clear</span>.<br>
                If suggestions stay empty, your Groq key or the configured chat model may not be available on your
                account — try a broadly available model such as <span class="font-mono">llama-3.3-70b-versatile</span>.</p>
            <div class="docs-section">
                <div class="docs-heading">When nothing transcribes</div>
                <p class="docs-text">The browser console showing <span class="font-mono">transcription_unavailable</span>
                    with <span class="font-mono">retryable: true</span> and no HTTP status means the request never
                    reached the provider. On Windows the usual cause is TLS verification: PHP's cURL keeps no
                    certificate store of its own unless <span class="font-mono">curl.cainfo</span> is set for the web
                    server, so a CA bundle that works in <span class="font-mono">artisan tinker</span> still fails in
                    the browser. Set <span class="font-mono">GROQ_CA_BUNDLE</span> to the absolute path of that
                    bundle, then run <span class="font-mono">config:clear</span>. The underlying reason is recorded
                    on the call's activity history as <span class="font-mono">call.provider_failed</span>.</p>
            </div>
        </div>

        <div class="docs-section">
            <div class="docs-heading">After the call: replay and the full conversation</div>
            <p class="docs-text">
                Every captured window is kept, whether or not it transcribed. That is deliberate: when the provider
                drops a window the page tells you the call is still being recorded, and the audio is what makes that
                true. The review page plays the call back — play, pause, seek, and a filter for your microphone, the
                meeting, or both — and every transcript line has a <span class="font-mono">▶</span> that jumps to
                the moment it was said.
            </p>
            <p class="docs-text">
                The review holds the whole exchange, not just the questions: what DeAlly said sits under the line
                that prompted it, the questions you asked it and its answers are kept, and the downloaded transcript
                carries the same. The <b>Heard</b> cards come back too, as a timeline of what the assistant was
                tracking, and the review task you get afterwards carries them as well — so you are not left
                scrolling a live panel trying to remember what it caught. Calls started before recording was kept
                cannot be replayed — the audio was never stored, so there is nothing to recover.
            </p>
            <p class="docs-text">
                Audio costs roughly 0.5 MB per minute of call and nothing prunes it, so a call's recording lives as
                long as the call does. DeAlly does not delete a recording on its own: a recording someone might
                still need is not something to tidy away on a schedule nobody agreed to.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Meeting platforms</div>
            <p class="docs-text">
                DeAlly can book a meeting and admit a transcription bot, but <b>no platform is connected out of the
                box and the app will say so</b>. It is not a limitation of the booking flow — the flow, the states,
                and the screens are all real — it is that creating a meeting and admitting a bot need credentials
                for a real Zoom, Teams, or Meet integration.
            </p>
            <p class="docs-text">
                A join link that looks real but is not would be worse than no link at all, because you would send
                it to a customer and wait for them at a meeting that does not exist. So a link is only ever shown
                when the provider actually returned one, and the bot is only ever reported as joined when the
                provider confirmed it. Any other outcome is written to the call with the reason, and the live-call
                header shows the platform's real state while the call is running.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Endpoints</div>
            <div class="code-block mono"><span class="code-method">POST</span> /app/calls/{call}/live/transcribe</div>
            <p class="docs-text">Multipart upload, <span class="font-mono">audio</span> field (max 10 MB). Returns the transcript, any new findings, and the stream's cards.</p>
            <div class="code-block mono" style="margin-top:10px;"><span class="code-method">POST</span> /app/calls/{call}/live/query</div>
            <p class="docs-text">JSON body with <span class="font-mono">text</span> (max 1000 chars). Returns an answer plus cards.</p>
        </div>
    </div>

    <footer class="system-footer">
        <span class="system-footer-copy">© {{ date('Y') }} DeAlly · AI-powered sales enablement · Wyzone Labs</span>
    </footer>
</div>

</body>
</html>