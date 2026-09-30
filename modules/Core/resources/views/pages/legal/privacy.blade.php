<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeAlly · Privacy Policy</title>
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
        <div class="login-sub">AI-powered sales enablement — Privacy Policy · effective September 30, 2026</div>

        <div style="display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap;">
            <a href="{{ route('legal.terms') }}" class="btn-sm {{ request()->routeIs('legal.terms') ? 'primary' : '' }}">Terms of Service</a>
            <a href="{{ route('legal.privacy') }}" class="btn-sm {{ request()->routeIs('legal.privacy') ? 'primary' : '' }}">Privacy Policy</a>
        </div>

        <div class="docs-section">
            <div class="docs-heading">1 · Overview</div>
            <p class="docs-text">
                This Privacy Policy explains what Wyzone Labs ("we", "us") collects when your
                organization uses DeAlly (the "Service"), why we collect it, and the choices you
                have. "You" means the organization that runs a DeAlly workspace and the people
                invited into it.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">2 · Information we collect</div>
            <ul class="docs-list">
                <li><b>Account data</b> — name, email address, role, and the workspace(s) you belong to.</li>
                <li><b>Workspace data</b> — call recordings (audio), transcripts, questions asked of the assistant, AI suggestions, findings and gap logs, knowledge base entries, proposals, pipeline records, tasks, and notes created in the Service.</li>
                <li><b>Usage data</b> — the pages and features you use, the device and browser type, IP address, and error logs needed to operate and fix the Service.</li>
                <li><b>Payment data</b> — if you subscribe to a paid plan, card details are handled by our payment processor; we do not store full card numbers.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">3 · How we use it</div>
            <ul class="docs-list">
                <li>Provide the Service: transcribe calls, analyze conversations, generate suggestions, and keep your records.</li>
                <li>Operate and secure the platform: support, troubleshooting, abuse prevention, and compliance with law.</li>
                <li>We do <b>not</b> use call recordings or workspace data to train AI models, and we do <b>not</b> sell personal data.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">4 · AI processing</div>
            <p class="docs-text">
                Call audio and conversation text are sent to third-party AI providers (such as
                OpenAI) to transcribe speech and to generate analysis and suggested replies. We
                send only the data needed for each request and follow each provider's API
                data-handling terms; under standard API agreements providers do not use this
                content to train their general models. See our
                <a href="{{ route('legal.terms') }}" style="color: var(--violet);">Terms of Service</a>
                for how AI output should be used.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">5 · How we share data</div>
            <p class="docs-text">
                We share data only with: processors who help run the Service (hosting, AI
                providers, payments, support), parties where disclosure is required by law or to
                protect legal rights, and an acquirer in the event of a business transfer. We do
                not sell or rent personal data.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">6 · Retention and deletion</div>
            <p class="docs-text">
                Call recordings and transcripts are kept while your workspace is active and for a
                short period afterwards, so replays and reviews stay available. Workspace owners
                can delete recordings and data at any time, and we honor deletion requests.
                Backups may retain data briefly for disaster recovery. When a workspace is
                deleted, its data is removed on our normal deletion schedule.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">7 · Security</div>
            <p class="docs-text">
                Data is encrypted in transit and at rest. The platform isolates each tenant's
                data, and workspace members only see what their role permits. Access to call
                recordings and transcripts is restricted to members of the workspace that owns
                them.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">8 · Your rights</div>
            <p class="docs-text">
                Depending on where you are, you may have rights to access, correct, export, or
                delete your personal data, and to object to or restrict certain processing. Owners
                and admins can exercise many of these directly in the product. For anything else,
                contact us below and we will respond within the timeframe the law requires.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">9 · Children</div>
            <p class="docs-text">
                The Service is a business tool and is not directed to children under 16. We do not
                knowingly collect personal data from children.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">10 · Changes to this Policy</div>
            <p class="docs-text">
                We may update this Policy from time to time. The effective date at the top of this
                page shows the latest revision, and material changes will be announced through the
                Service.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">11 · Contact</div>
            <p class="docs-text">
                Questions about this Policy: <span class="mono">privacy@deally.app</span> or
                Wyzone Labs, via the workspace owner dashboard.
            </p>
        </div>
    </div>
</div>

</body>
</html>