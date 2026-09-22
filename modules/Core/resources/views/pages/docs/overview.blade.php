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
                    <tr><td>Calls are a black box</td><td>Transcripts, objection flags, and summaries capture everything</td></tr>
                    <tr><td>Winning talk tracks live inside one rep's head</td><td>Best answers become reusable playbook cards</td></tr>
                    <tr><td>Note-taking pulls focus from the conversation</td><td>Transcription and summaries are automatic</td></tr>
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
                <li><b>Calls</b> — log calls, live AI-assisted sessions, reviews, and summaries.</li>
                <li><b>Pipeline</b> — opportunities through the sales pipeline.</li>
                <li><b>Tasks</b> — follow-up tasks with one-click completion.</li>
                <li><b>Proposals</b> — documents tied to calls and opportunities.</li>
                <li><b>Knowledge Base</b> — the source of truth the AI answers from.</li>
                <li><b>Reporting</b> — team performance, account, coaching, and task reporting.</li>
                <li><b>Admin</b> — users, roles, and teams scoped to the tenant.</li>
                <li><b>Ask DeAlly</b> — a global command bar (⌘K) that answers any question from the knowledge base.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">The flagship feature: live AI call assistant</div>
            <p class="docs-text">The agent opens the live call screen, presses Start, and DeAlly takes over the busywork:</p>
            <ol class="docs-list">
                <li><b>Listen</b> — the mic streams short audio chunks to the server.</li>
                <li><b>Transcribe</b> — each chunk becomes a transcript line (OpenAI Whisper).</li>
                <li><b>Suggest</b> — for every customer line the AI proposes two KB-grounded replies on the Findings panel.</li>
                <li><b>Ask</b> — the agent can type any ad-hoc question and get a plain-English answer to read to the customer.</li>
                <li><b>Object</b> — flag an objection; if the AI can't answer confidently it opens a knowledge gap, alerts the Solutions Lead, and tells the agent what to ask.</li>
                <li><b>End</b> — press End Call; the transcript, review, and summary are ready automatically.</li>
            </ol>
            <p class="docs-text">With no API key the simulated driver keeps the whole flow working for demos. See the <a href="{{ route('docs.ai') }}">AI setup guide</a>.</p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">A knowledge loop that improves every call</div>
            <p class="docs-text">
                When the AI hits a question it can't answer with confidence, it creates a <b>knowledge gap</b>
                and notifies the Solutions Lead. The lead adds the answer to the knowledge base — closing the
                loop so the next call handles the same objection automatically.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">Architecture in brief</div>
            <ul class="docs-list">
                <li>Laravel multi-tenant SaaS; a central database holds users, tenants, and memberships.</li>
                <li>Each active tenant runs its <b>own isolated database</b>.</li>
                <li>Feature modules under <span class="font-mono">modules/</span> (Calls, Pipeline, Tasks, Proposals, Reporting, Settings, Core…).</li>
                <li>Frontend: Blade + Vite with a built-in light/dark theme.</li>
            </ul>
        </div>
    </div>
</div>

</body>
</html>