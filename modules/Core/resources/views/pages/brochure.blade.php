<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title></title>
    <style>
        /* Font paths must stay absolute and server-resolved, so they are built
           here rather than in resources/css/brochure.css. */
        @font-face { font-family: 'ibm-plex-sans'; font-style: normal; font-weight: 400; src: url('{{ str_replace('\\', '/', storage_path('fonts/IBMPlexSans-Regular.ttf')) }}') format('truetype'); }
        @font-face { font-family: 'ibm-plex-sans'; font-style: normal; font-weight: 600; src: url('{{ str_replace('\\', '/', storage_path('fonts/IBMPlexSans-SemiBold.ttf')) }}') format('truetype'); }
        @font-face { font-family: 'ibm-plex-sans'; font-style: normal; font-weight: 700; src: url('{{ str_replace('\\', '/', storage_path('fonts/IBMPlexSans-Bold.ttf')) }}') format('truetype'); }
        @font-face { font-family: 'ibm-plex-sans'; font-style: italic; font-weight: 400; src: url('{{ str_replace('\\', '/', storage_path('fonts/IBMPlexSans-Italic.ttf')) }}') format('truetype'); }
        @font-face { font-family: 'ibm-plex-mono'; font-style: normal; font-weight: 400; src: url('{{ str_replace('\\', '/', storage_path('fonts/IBMPlexMono-Regular.ttf')) }}') format('truetype'); }

        {!! $stylesheet !!}
    </style>
</head>
<body>

    <!-- ===================== PAGE 1 · COVER ===================== -->
    <div class="page">
        <div class="page-bg"></div>
        <div class="brandbar">
            <table style="width:100%"><tr>
                <td><div class="wordmark">De<b>Ally</b></div></td>
                <td style="text-align:right"><div class="brandsub">by WYZONE LABS<br>sales intelligence, live</div></td>
            </tr></table>
        </div>

        <div class="hero">
            <div class="label">The sales platform with a live AI copilot</div>
            <h1>Every sales call, captured.<br>Every AI answer, <span style="color:#00a2ed">grounded</span>.</h1>
            <p class="lede">
                DeAlly is a multi-tenant sales workspace and a real-time AI assistant in one — calls,
                pipeline, proposals and knowledge in a single place, and an AI that never puts words in
                your mouth it can&rsquo;t back up.
            </p>
            <div class="cta-band">LIVE AI COPILOT&nbsp;&nbsp;·&nbsp;&nbsp;REAL-TIME TRANSCRIPTION&nbsp;&nbsp;·&nbsp;&nbsp;
                KNOWLEDGE-GROUNDED ANSWERS&nbsp;&nbsp;·&nbsp;&nbsp;<b>NOTHING INVENTED</b></div>
        </div>

        <table class="grid">
            <tr>
                <td><div class="tile">
                    <div class="tile-title"><span class="num">01</span>Live AI copilot</div>
                    <p>Dual-stream audio capture, neural transcription and a findings shelf —
                    say, ask, reference, waiting and objection cards — grounded in your knowledge base.</p>
                </div></td>
                <td><div class="tile">
                    <div class="tile-title"><span class="num">02</span>Complete call lifecycle</div>
                    <p>Booking, verbatim invitations, the live session, post-call summary, replay, and a
                    review task that scores agent performance.</p>
                </div></td>
                <td><div class="tile">
                    <div class="tile-title"><span class="num">03</span>Pipeline · Proposals · Knowledge</div>
                    <p>Accounts, contact, deals and risk tiers, editable proposals, and a governed knowledge
                    base sustained by a Solutions queue.</p>
                </div></td>
            </tr>
        </table>

        <div class="stats">
            <table style="width:100%"><tr>
                <td style="width:25%"><span class="stat"><b>20</b>product guarantees enforced in code</span></td>
                <td style="width:25%"><span class="stat"><b>8</b>feature modules, one workspace</span></td>
                <td style="width:25%"><span class="stat"><b>5</b>live findings card kinds</span></td>
                <td style="width:25%"><span class="stat"><b>2</b>audio streams per call</span></td>
            </tr></table>
        </div>

        <div class="section" style="margin-top:14pt">
            <div class="label">How DeAlly works — in under a minute</div>
            <table class="grid" style="margin-top:2pt">
                <tr>
                    <td><div class="tile">
                        <div class="tile-title"><span class="num">01</span>Listen</div>
                        <p>Dual audio streams transcribed live — the mic and the meeting — with silence dropped and nothing fabricated.</p>
                    </div></td>
                    <td><div class="tile">
                        <div class="tile-title"><span class="num">02</span>Recommend</div>
                        <p>Grounded answers and findings cards surface as they matter — prices, terms and facts are never invented.</p>
                    </div></td>
                    <td><div class="tile">
                        <div class="tile-title"><span class="num">03</span>Loop</div>
                        <p>Unresolved questions become knowledge-base answers, so the next call is smarter automatically.</p>
                    </div></td>
                </tr>
            </table>
        </div>

        <div class="section" style="margin-top:12pt">
            <div class="label">Who it&rsquo;s for</div>
            <div class="chips" style="margin-top:6pt">
                <span class="chip">SALES REPS</span>
                <span class="chip">SALES LEADERSHIP</span>
                <span class="chip">SOLUTIONS TEAMS</span>
                <span class="chip">TENANT OWNERS</span>
            </div>
        </div>

        <div class="pagefoot">
            <table style="width:100%"><tr>
                <td>DEALLY · marketing brochure</td>
                <td class="right">Page 1 / 3</td>
            </tr></table>
        </div>
    </div>

    <!-- ===================== PAGE 2 · THE PRODUCT ===================== -->
    <div class="page">
        <div class="page-bg"></div>
        <div class="brandbar">
            <table style="width:100%"><tr>
                <td><div class="wordmark">De<b>Ally</b></div></td>
                <td style="text-align:right"><div class="brandsub">THE PRODUCT</div></td>
            </tr></table>
        </div>

        <div class="section">
            <h2>The live AI assistant</h2>
            <p class="sub">DeAlly listens while the agent talks — transcribing both sides of the call in
            real time, then analyzing what the newest line changes for the deal.</p>
            <ul class="list">
                <li><b>Real-time transcription</b> of both audio streams — silent windows are dropped,
                    retries are idempotent, nothing is fabricated.</li>
                <li><b>A findings shelf</b> that surfaces what matters right now as cards: <b>say</b>,
                    <b>ask</b>, <b>reference</b>, <b>waiting</b> and <b>objection</b>.</li>
                <li><b>Heard</b> — a one-line paraphrase of what was just said, never a quoted transcript.</li>
                <li><b>Ask DeAlly</b> — a long-question box (up to 4,000 characters) that keeps the whole
                    question, not a truncated opener.</li>
                <li><b>Knowledge grounding.</b> Answers come from the knowledge base; everyday how-tos are
                    answered from the model&rsquo;s own knowledge and labelled <em class="primary">verify before
                    quoting</em> — your prices, terms and features are never invented.</li>
                <li><b>The gap loop.</b> Unresolved questions queue to the Solutions lead; approve an answer once
                    and the same question is answered everywhere from then on.</li>
            </ul>
            <div class="chips">
                <span class="chip say">SAY</span><span class="chip ask">ASK</span><span class="chip reference">REFERENCE</span>
                <span class="chip waiting">WAITING</span><span class="chip objection">OBJECTION</span>
            </div>
        </div>

        <div class="section">
            <h2>Calls: from booking to review</h2>
            <p class="sub">The whole lifecycle is tracked — and the review is the real work, not a checkbox.</p>
            <table class="timeline" style="width:100%"><tr>
                <td class="step">BOOK</td><td class="arrow">›</td>
                <td class="step">INVITE</td><td class="arrow">›</td>
                <td class="step">LIVE</td><td class="arrow">›</td>
                <td class="step">SUMMARY</td><td class="arrow">›</td>
                <td class="step">REVIEW</td><td class="arrow">›</td>
                <td class="step">REPLAY</td>
            </tr></table>
            <ul class="list" style="margin-top:6pt">
                <li><b>Invitations are verbatim and tracked</b> — what was actually sent is stored, delivery
                    failures recorded.</li>
                <li><b>Missed calls raise reschedule tasks</b>, so a dead deal never just sits.</li>
                <li><b>Review task</b> with an Agent Performance score — talk ratio, objections handled,
                    suggestion usefulness — that cannot close while a deal flag is open.</li>
                <li><b>Replay</b> every captured window, jumping to the moment any line was said.</li>
            </ul>
        </div>

        <div class="section">
            <h2>Pipeline, customers &amp; tasks</h2>
            <ul class="list">
                <li><b>Customer accounts and contacts</b> with deals attached — one customer, many deals,
                    a permanent engagement log of every stage move, lost reason and note.</li>
                <li><b>Risk tiers</b> — Critical, High, Medium, Low — driven by missed service reviews,
                    open proposals and stalled negotiation.</li>
                <li><b>Service reviews</b> on a per-account cadence that refill as sessions are held.</li>
                <li><b>Tasks with explicit due times</b> — a task is todo or closed, nothing else.</li>
            </ul>
            <div class="chips">
                <span class="chip" style="color:#f87171">CRITICAL</span>
                <span class="chip" style="color:#e8a22b">HIGH</span>
                <span class="chip" style="color:#a78bfa">MEDIUM</span>
                <span class="chip" style="color:#4ade80">LOW</span>
            </div>
        </div>

        <div class="pagefoot">
            <table style="width:100%"><tr>
                <td>DEALLY · the product</td>
                <td class="right">Page 2 / 3</td>
            </tr></table>
        </div>
    </div>

    <!-- ===================== PAGE 3 · PLATFORM & TRUST ===================== -->
    <div class="page last">
        <div class="page-bg"></div>
        <div class="brandbar">
            <table style="width:100%"><tr>
                <td><div class="wordmark">De<b>Ally</b></div></td>
                <td style="text-align:right"><div class="brandsub">THE PLATFORM</div></td>
            </tr></table>
        </div>

        <div class="section">
            <h2>A workspace for the whole team</h2>
            <ul class="list">
                <li><b>Workspace &amp; reporting</b> — a team day view, seat-scoped dashboards, account story and
                    coaching review for managers.</li>
                <li><b>Knowledge base</b> — searchable and paginated, the single source the AI is allowed to
                    ground on, written only through governed approvals.</li>
                <li><b>Solutions lead</b> — a gap queue, a corrections log, and a 6-month voice-of-customer
                    heatmap across every call.</li>
                <li><b>VOC trends</b> — sentiment and readiness aggregated per tenant, surfaced as trends.</li>
            </ul>
        </div>

        <div class="section">
            <h2>Built for teams — roles &amp; multi-tenancy</h2>
            <p class="sub">Every seat sees only its scope. Every tenant runs its own database.</p>
            <table class="seats">
                <tr><th>Seat</th><th>Sees</th><th>Owns</th></tr>
                <tr><td>Sales Agent</td><td>own customers, deals, tasks, calls, proposals</td><td>own pipeline, tasks, calls, proposals</td></tr>
                <tr><td>Team Leader</td><td>their team&rsquo;s records, team health, approvals</td><td>coaching, reassignment, approvals</td></tr>
                <tr><td>Solutions Lead</td><td>org-wide knowledge base, gap &amp; correction queues</td><td>governed KB approvals and edits</td></tr>
                <tr><td>Admin / Owner</td><td>team structure, users, integrations, settings</td><td>account-level configuration</td></tr>
            </table>
        </div>

        <div class="section">
            <h2>Guaranteed by design</h2>
            <p class="sub">Twenty invariants the test suite keeps true — the ones that matter most to your deals:</p>
            <div class="guarantee"><b>NO INVENTED FACTS</b> <span>— AI answers come from the knowledge base; prices and
                terms are never guessed.</span></div>
            <div class="guarantee"><b>NOTHING IS DELETED</b> <span>— cards hide, findings persist, corrections are logged
                permanently.</span></div>
            <div class="guarantee"><b>CONSENT BY INVITATION</b> <span>— the invitation that was actually sent is what
                counts, recorded verbatim.</span></div>
            <div class="guarantee"><b>TRANSPARENT STATE</b> <span>— the assistant always announces what it is doing;
                the shelf shows cards, never a raw transcript.</span></div>
            <div class="guarantee"><b>ERRORS STAY VISIBLE</b> <span>— failures surface to the person who triggered
                them, on the call itself.</span></div>
            <div class="guarantee"><b>STEERING, NOT ECHOING</b> <span>— DeAlly guides the agent, it does not repeat the
                customer.</span></div>
        </div>

        <div class="cta-end">
            <table style="width:100%"><tr>
                <td>
                    <div class="big">See it on a live call.</div>
                    <div class="mono" style="margin-top:3pt">deally.test · wyzone labs · sales intelligence, live</div>
                </td>
                <td style="text-align:right"><div class="mono">BOOK A DEMO<br>›</div></td>
            </tr></table>
        </div>

        <div class="pagefoot">
            <table style="width:100%"><tr>
                <td>&copy; {{ now()->year }} Wyzone Labs. All rights reserved. DeAlly is a registered brand of Wyzone Labs.</td>
                <td class="right">Page 3 / 3</td>
            </tr></table>
        </div>
    </div>

</body>
</html>
