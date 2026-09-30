<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeAlly · Product Overview</title>
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
        <div class="login-sub">AI-powered sales enablement — product overview</div>

        <div style="display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap;">
            <a href="{{ route('docs.overview') }}" class="btn-sm {{ request()->routeIs('docs.overview') ? 'primary' : '' }}">Product Overview</a>
            <a href="{{ route('docs.calls') }}" class="btn-sm {{ request()->routeIs('docs.calls') ? 'primary' : '' }}">Call Lifecycle</a>
            <a href="{{ route('docs.ai') }}" class="btn-sm {{ request()->routeIs('docs.ai') ? 'primary' : '' }}">Live AI Assistant</a>
        </div>

        <div class="docs-section">
            <div class="docs-heading">What is DeAlly?</div>
            <p class="docs-text">
                DeAlly is a multi-tenant SaaS platform for sales teams that pairs classic sales tools
                (calls, pipeline, tasks, proposals) with a <b>live AI assistant</b>. During a sale it
                listens to the conversation, transcribes it, and feeds the agent the exact words to say —
                grounded in the company's knowledge base. The name says it all: DeAlly is the sales
                team's <b>ally</b>, always listening and always ready with the next best thing to say.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">The problem it solves</div>
            <table class="docs-table">
                <thead>
                    <tr><th>Without DeAlly</th><th>With DeAlly</th></tr>
                </thead>
                <tbody>
                    <tr><td>Agents improvise responses mid-call</td><td>Read AI-suggested, knowledge-grounded replies live</td></tr>
                    <tr><td>Customers repeat questions the team already answered</td><td>Answers draw on a shared knowledge base</td></tr>
                    <tr><td>Calls are a black box</td><td>Transcripts, objection flags, and a review task that can't be closed while a decision is outstanding</td></tr>
                    <tr><td>Winning talk tracks live inside one rep's head</td><td>Best answers become reusable playbook cards</td></tr>
                    <tr><td>Note-taking pulls focus from the conversation</td><td>Transcription and findings are automatic, and the recording is kept for review</td></tr>
                    <tr><td>A meeting link that doesn't work gets sent anyway</td><td>DeAlly only reports a join link or a joined bot when a real provider confirmed it</td></tr>
                </tbody>
            </table>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Who is it for?</div>
            <ul class="docs-list">
                <li><b>Sales reps / agents</b> — live script assistance, instant answers, objection handling.</li>
                <li><b>Sales leadership</b> — team performance, coaching review, pipeline and proposal reporting.</li>
                <li><b>Solutions teams</b> — own the knowledge base that grounds every AI answer; get alerted to knowledge gaps.</li>
                <li><b>Tenant owners</b> — manage users, roles, teams, and data retention.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Platform at a glance</div>
            <ul class="docs-list">
                <li><b>Home</b> — landing dashboard for the signed-in user.</li>
                <li><b>Calls</b> — schedule and invite a call, run a live AI-assisted session, replay the recording, and turn the review into a task.</li>
                <li><b>Pipeline</b> — opportunities with a list/board toggle, owner column, and per-deal risk flags.</li>
                <li><b>Customers</b> — accounts with contacts, ownership, and an at-risk panel.</li>
                <li><b>Service Reviews</b> — recurring account check-ins with reschedule, hold, and catch-up.</li>
                <li><b>Tasks</b> — follow-up tasks with one-click completion; the per-call review task opens in a modal.</li>
                <li><b>Proposals</b> — documents tied to calls and opportunities, editable in place.</li>
                <li><b>Knowledge Base</b> — the source of truth the AI answers from; a searchable, paginated flashcard library where each card opens to a full view.</li>
                <li><b>Reporting</b> — team performance, account, coaching, and task reporting.</li>
                <li><b>Admin</b> — users, roles, and teams scoped to the tenant, with a reassignment plan for removed sales agents.</li>
                <li><b>Ask DeAlly</b> — a global command bar (⌘K) that answers any question from the knowledge base.</li>
                <li><b>Legal</b> — public Terms of Service and Privacy Policy pages, linked from the login footer next to the docs.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">The flagship feature: live AI call assistant</div>
            <p class="docs-text">The agent opens the live call screen, presses Start, and DeAlly takes over the busywork:</p>
            <ol class="docs-list">
                <li><b>Listen</b> — the mic, and the shared meeting audio, stream short audio chunks to the server as two independent sources.</li>
                <li><b>Transcribe</b> — each 4-second chunk becomes a transcript line (Groq or OpenAI Whisper). A long question that runs across several chunks is assembled in full rather than cut off; silence is a no-op, not an invented line.</li>
                <li><b>Analyse</b> — every few seconds the recent conversation is scanned for buying signals, intent, objections, competitors, risk, and knowledge gaps, and for short grounded replies.</li>
                <li><b>Show</b> — findings land on the Findings panel, and a one-line paraphrase of what was just said appears in the stream beside it.</li>
                <li><b>Ask</b> — the agent can type any ad-hoc question and get a plain-English answer to read to the customer. The box grows with the question (up to 4,000 characters; Enter sends, Shift+Enter starts a new line), so a long ask is never cut off.</li>
                <li><b>Object</b> — flag an objection; if the AI can't answer confidently it opens a knowledge gap, alerts the Solutions Lead, and tells the agent what to ask.</li>
                <li><b>End</b> — press End Call; the transcript, the recording, and a short summary are ready automatically.</li>
            </ol>
            <p class="docs-text">With no API key the simulated driver keeps the whole flow working for demos. See the <a href="{{ route('docs.ai') }}">AI setup guide</a>.</p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">The call lifecycle</div>
            <p class="docs-text">
                A call is a chain, and the weakest link decides whether the chain is worth anything.
                DeAlly's booking and review flow is built around one rule: <b>a rep must never be told
                something that did not happen.</b>
            </p>
            <ul class="docs-list">
                <li><b>Booking</b> — pick a meeting platform and a session type. The invitation is composed, sent, and stored verbatim on the call, so what was actually sent stays answerable.</li>
                <li><b>Unplanned calls</b> — a failed or missed call is recorded with its reason and raises a reschedule task, so a no-show doesn't quietly leave a dead deal behind.</li>
                <li><b>The summary</b> — deliberately short: real readiness, open deal-status flags, whether a proposal was agreed, and a way into the review task. The review is work, and work is a task.</li>
                <li><b>The review task</b> — what DeAlly heard, the objections it caught and the ones you added, what the call left outstanding, correctable sentiment and readiness, and the open flags.</li>
            </ul>
            <p class="docs-text">
                Two things are deliberately gated. <b>Create Proposal</b> appears only when the
                conversation actually agreed a proposal, because a button on every call is a suggestion
                you have to evaluate and discard. And the <b>review task cannot be closed while a
                deal-status flag is open</b> — from the modal or the list, both of which say why. A
                flag that does not block anything is a note.
            </p>
            <p class="docs-text">
                Correcting a judgement never overwrites the model's. DeAlly keeps what the AI believed
                alongside what you said was true, and the difference is recorded. That difference is the
                only part of a correction worth learning from.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Meeting platforms — what works and what doesn't yet</div>
            <p class="docs-text">
                <b>No meeting platform is connected, and the app says so.</b> The picker, the booking
                flow, the states, and the screens are all real. Creating a meeting and admitting a
                transcription bot need credentials for a real Zoom, Teams, or Meet integration, and
                those cannot be simulated.
            </p>
            <p class="docs-text">
                A connector that returned a well-formed-looking join link would be worse than having
                none at all, because you would send it to a customer and believe they had it. So a join
                link is only ever written from a real provider response, and the bot is only ever
                reported as joined when the provider confirms it. Everything else is stored on the
                call with a plain-language reason, and the live-call header shows the platform's real
                state while the call is running.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">A knowledge loop that improves every call</div>
            <p class="docs-text">
                When the AI hits a question it can't answer with confidence, it creates a <b>knowledge gap</b>
                and notifies the Solutions Lead. The lead adds the answer to the knowledge base — closing the
                loop so the next call handles the same objection automatically.
            </p>
            <p class="docs-text">
                Marking a finding <b>unhelpful</b> feeds the same loop, and an objection you add during
                review is kept apart from the ones the model caught. The second kind is the expensive one:
                it is the model missing something.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Architecture in brief</div>
            <ul class="docs-list">
                <li>Laravel multi-tenant SaaS; a central database holds users, tenants, and memberships.</li>
                <li>Each active tenant runs its <b>own isolated database</b>, so a tenant table cannot constrain itself to a central one — a user id on a tenant table is an indexed column, not a foreign key.</li>
                <li>Feature modules under <span class="font-mono">modules/</span> (Calls, Pipeline, Tasks, Proposals, Reporting, Settings, Core…); the Pipeline module also owns customers, contacts, and Service Reviews.</li>
                <li>Frontend: Blade + Vite with a built-in light/dark theme.</li>
                <li>Provider credentials, provider calls, and response validation all stay on the server. The browser only sends audio.</li>
            </ul>
        </div>
    </div>

    <footer class="system-footer">
        <span class="system-footer-copy">© {{ date('Y') }} DeAlly · AI-powered sales enablement · Wyzone Labs</span>
    </footer>
</div>

</body>
</html>