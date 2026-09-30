<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeAlly · Terms of Service</title>
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
        <div class="login-sub">AI-powered sales enablement — Terms of Service · effective September 30, 2026</div>

        <div style="display: flex; gap: 8px; margin: 16px 0 24px; flex-wrap: wrap;">
            <a href="{{ route('legal.terms') }}" class="btn-sm {{ request()->routeIs('legal.terms') ? 'primary' : '' }}">Terms of Service</a>
            <a href="{{ route('legal.privacy') }}" class="btn-sm {{ request()->routeIs('legal.privacy') ? 'primary' : '' }}">Privacy Policy</a>
        </div>

        <div class="docs-section">
            <div class="docs-heading">1 · Agreement</div>
            <p class="docs-text">
                These Terms of Service ("Terms") govern your access to and use of DeAlly (the
                "Service"), operated by Wyzone Labs ("we", "us", "our"). By creating an account,
                you agree to these Terms. If you use the Service on behalf of an organization,
                you agree to these Terms on its behalf and confirm that you are authorized to do so.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">2 · The Service</div>
            <p class="docs-text">
                DeAlly is a sales enablement platform that pairs sales tools — calls, pipeline,
                tasks, proposals — with a live AI assistant. During a call it can record audio,
                transcribe the conversation, analyze it for signals (buying intent, objections,
                competitors, risks, knowledge gaps), and suggest knowledge-grounded replies drawn
                from your organization's knowledge base.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">3 · Accounts and workspaces</div>
            <p class="docs-text">
                To use the Service you need an account in an organizational workspace (a "tenant").
                Owners and admins control the roles, seats and permissions of other members. You
                are responsible for keeping your credentials confidential and for all activity that
                occurs under your account. Tell us promptly if you suspect unauthorized use.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">4 · Your data and recordings</div>
            <p class="docs-text">
                Your workspace owns the data you put into it: knowledge base entries, proposals,
                pipeline records, call transcripts, and recordings. When a rep records a call, we
                process the audio to produce a transcript and keep both so the call can be reviewed
                and analyzed later. We act as a processor of workspace data on your instructions.
                You must have the right to record and process the conversations you capture, and
                you are responsible for informing participants where the law requires it.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">5 · Acceptable use</div>
            <ul class="docs-list">
                <li>Store or transmit unlawful, infringing, or harmful content.</li>
                <li>Record or observe participants without the consent the law requires.</li>
                <li>Probe, scan, or attempt to breach the security of the Service or other tenants.</li>
                <li>Reverse-engineer, decompile, or extract the source code of the Service.</li>
                <li>Resell or sublicense access to the Service except as agreed in writing.</li>
            </ul>
        </div>

        <div class="docs-section">
            <div class="docs-heading">6 · AI features</div>
            <p class="docs-text">
                The live assistant generates suggestions from your knowledge base and the
                conversation so far. AI output is generated and can be wrong, outdated, or
                inappropriate. It is decision support, not legal, financial, or compliance advice.
                You are responsible for what is said on your calls, and you should confirm any
                claim — especially about pricing and commitments — before making it to a customer.
                Transcription and analysis are provided by third-party AI providers; see our
                <a href="{{ route('legal.privacy') }}" style="color: var(--violet);">Privacy Policy</a>.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">7 · Paid plans</div>
            <p class="docs-text">
                Paid plans are billed in advance for the term you choose. You can change or cancel
                your plan from your workspace settings. Fees are generally non-refundable except
                where required by law. We may change prices for future terms with reasonable notice.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">8 · Termination</div>
            <p class="docs-text">
                You can stop using the Service and delete your workspace at any time. We may
                suspend or terminate access for breach of these Terms or extended non-payment,
                with notice where practical. After termination you can export your workspace data
                during a grace window, after which it is deleted.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">9 · Intellectual property</div>
            <p class="docs-text">
                The Service, including its software, interface, and branding, is owned by Wyzone
                Labs. These Terms grant you a limited, non-exclusive right to use the Service;
                nothing else. You retain all rights in your workspace data.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">10 · Disclaimers</div>
            <p class="docs-text">
                The Service is provided "as is" and "as available", without warranties of any
                kind, express or implied, including fitness for a particular purpose. We do not
                guarantee that AI output is accurate or that the Service will be uninterrupted or
                error-free. Transcription quality depends on audio quality and on third-party
                providers.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">11 · Limitation of liability</div>
            <p class="docs-text">
                To the maximum extent permitted by law, Wyzone Labs is not liable for indirect,
                incidental, special, or consequential damages, or for loss of profits, data, or
                goodwill, arising from the Service. Our total liability for any claim is limited
                to the amounts you paid us in the three months before the claim.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">12 · Changes to these Terms</div>
            <p class="docs-text">
                We may update these Terms from time to time. Material changes will be announced
                through the Service or by email. Continued use of the Service after the effective
                date constitutes acceptance of the updated Terms.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">13 · Governing law</div>
            <p class="docs-text">
                These Terms are governed by the laws of the State of Delaware, without regard to
                its conflict-of-law rules, and any disputes will be resolved in the courts of
                Delaware.
            </p>
        </div>

        <div class="docs-section">
            <div class="docs-heading">14 · Contact</div>
            <p class="docs-text">
                Questions about these Terms: <span class="mono">legal@deally.app</span> or
                Wyzone Labs, via the workspace owner dashboard.
            </p>
        </div>
    </div>

    <footer class="system-footer">
        <span class="system-footer-copy">© {{ date('Y') }} DeAlly · AI-powered sales enablement · Wyzone Labs</span>
    </footer>
</div>

</body>
</html>