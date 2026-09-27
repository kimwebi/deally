# DeAlly — System Overview & Product Presentation

> **One-sentence pitch:** DeAlly is an AI-powered sales enablement platform that listens to your sales
> calls in real time, suggests what to say next, and turns every conversation into repeatable
> pipeline, proposals, and playbooks.

---

## 1. What is DeAlly?

DeAlly is a multi-tenant SaaS application for modern sales teams. It combines classic CRM/sales
tools (calls, pipeline, tasks, proposals) with a **live AI assistant** that works beside the agent:
it captures both sides of the conversation, transcribes the audio on the server, detects meaningful
sales signals, and turns the tenant's knowledge base into grounded, spoken guidance.

The name says it all: the system is a **sales ally** — always listening, always ready with the next
best thing to say.

### The problem it solves

| Without DeAlly | With DeAlly |
| --- | --- |
| Agent improvises responses during calls | Agent reads short, AI-suggested, knowledge-grounded replies live |
| Customers repeat questions the team already answered elsewhere | Every answer draws on the tenant's shared knowledge base |
| Calls are a black box ("what did they object to?") | Transcripts and evidence-linked buying, intent, objection, competitor, risk, and gap signals are captured automatically |
| Winning talk tracks live inside one rep's head | Rep feedback turns weak suggestions into corrections and reusable knowledge |
| Manual note-taking takes attention away from the conversation | Dual-stream transcription and findings reduce post-call cleanup and create a review trail |

---

## 2. Who is it for?

- **Sales reps / agents** — live script assistance, instant answers, objection handling.
- **Sales leadership / managers** — team performance, coaching review, pipeline and proposal reporting.
- **Solutions teams** — own the knowledge base that grounds every AI answer; review linked knowledge gaps and rep feedback.
- **Tenant owners** — manage users, roles, teams, and data retention from a single control plane.

---

## 3. Platform at a glance

DeAlly is modular. Each area of the product is its own module under `modules/`, so capabilities can
be built, tested, and shipped independently.

| Area | What it does |
| --- | --- |
| **Home (Workspace)** | Landing dashboard for the signed-in user. |
| **Calls** | Log calls, capture agent + meeting audio, transcribe both speakers, surface live findings, and review transcripts/summaries. |
| **Pipeline** | Track opportunities with a list or board view, owner column, and per-deal risk flags. |
| **Customers** | Customer accounts with owners, multiple contacts (single primary), an at-risk panel, and a pre-filled Create Deal modal. |
| **Service Reviews** | Recurring per-customer check-in schedule with reschedule, hold, cancel, and catch-up. |
| **Tasks** | Personal/team follow-up tasks with one-click toggle. |
| **Proposals** | Build and edit proposals tied to calls, opportunities, and the knowledge base. |
| **Knowledge Base (KB)** | The single source of truth the AI answers from (pricing, features, processes). |
| **Reporting** | Team performance, account-level, coaching, and task reporting. |
| **Activity** | Audit-style activity feed of important system events. |
| **Notifications** | In-app call/lifecycle notifications with read state; pending AI gaps also surface in Workspace and the Solutions queue. |
| **Settings** | Profile/preferences, the demand-account threshold, and admin (users, roles, teams) for tenant owners. |
| **Tenant Management** | Platform admin: create, edit, and clone tenant instances. |
| **Docs** | Public AI setup documentation page (also reachable from the login footer). |

---

## 4. The flagship feature: live AI call assistant

This is the differentiator. DeAlly sits in the call with the agent without replacing the existing
Live Call workspace: captured speech becomes a durable transcript, and a lightweight analysis loop
turns the latest customer context into evidence-linked findings.

### How a live call works

1. **Start capture** — the agent clicks **Start** and grants microphone permission. The browser
   separately requests shared meeting audio; if sharing is declined, the call continues with the
   agent microphone alone.
2. **Two independent audio sources** — local microphone is labeled **agent**, and shared meeting
   audio is labeled **customer**. Each source is recorded and uploaded independently, so a slow
   transcription request never pauses the other recorder.
3. **Continuous server-side transcription** — `MediaRecorder` combines each source into 6-second
   chunks. Silent windows are dropped in the browser before upload, and DeAlly sends the rest to the
   configured provider, stores usable text as a `TranscriptLine`, and treats silence as a normal
   no-op rather than inventing a line.
4. **Structured conversation analysis** — after customer chunks, a throttled rolling-window request
   (eight recent lines by default, no more often than every 10 seconds per call) detects buying
   signals, intent, objections, competitors, deal risk, and knowledge gaps.
5. **Knowledge-grounded recommendations** — the same analysis returns short **say**, **ask**,
   **reference**, or **objection** guidance grounded in the tenant's Knowledge Base.
6. **Persistent Findings shelf** — accepted signals and recommendations are validated again on the
   server, deduplicated, linked to a stored transcript line, and rendered as cards. Reopening the
   call restores the shelf immediately.
7. **Feedback improves the workflow** — the agent can mark a persisted finding **unhelpful**. That
   status is stored and a linked correction gap enters the Solutions queue.
8. **Ask or flag on demand** — the agent can ask DeAlly a free-form question or flag an objection;
   both use the recent transcript and Knowledge Base context. Missing answers become knowledge
   gaps rather than unsupported claims.
9. **End without losing the tail** — End Call stops both recorders, flushes their final chunks,
   waits for both upload queues, then completes the call and opens its summary/review flow.

```text
Agent mic ──▶ 8s agent chunks ───┐
                                ├─▶ server transcription ─▶ transcript lines
Meeting audio ─▶ 8s customer chunks ┘                         │
                                                               ▼
                                            throttled structured analysis
                                              signals + spoken recommendations
                                                               │
                                  persisted findings ◀─────────┘
                                     │               │
                            agent feedback     knowledge gaps
```

The browser submits audio only to DeAlly. Provider credentials, endpoints, speech-to-text calls,
LLM calls, and response validation remain on the Laravel server.

### AI drivers

- **Groq** — preferred real driver in `auto` mode, using Groq's OpenAI-compatible API.
- **OpenAI** — supported alternative through the same provider-configurable assistant.
- **Dummy** — explicit or non-production demo driver with no provider requests. It can run the
  scripted narrative and seed a reviewable demo conversation, but it never fabricates a transcript
  from captured audio.

`LIVE_AI_DRIVER` accepts `auto`, `groq`, `openai`, or `dummy`. Production never silently replaces a
missing real provider with demo AI.

### Where findings come from

Every Knowledge Base entry is included in the analysis, suggestion, and answer prompts. The model is
instructed to use only what the transcript and KB support, and Laravel independently validates all
structured output before storing it. A well-maintained KB and the rep's unhelpful-feedback loop make
the assistant more useful over time.

See `docs/ai-llm-integration.md` for the complete browser flow, endpoints, configuration, output
schema, persistence model, and security notes.

---

## 5. Knowledge gaps — feedback loop that gets smarter every call

DeAlly turns three kinds of uncertainty into actionable Solutions work:

1. **Automatic gap detection** — a customer chunk produces a validated `knowledge_gap` signal when
   the requested answer is not supported by the Knowledge Base.
2. **Objection follow-up** — when the rep flags an objection and the assistant has no documented
   response, DeAlly records the objection and returns a clarifying prompt.
3. **Unhelpful finding feedback** — a rep can mark a persisted live finding **unhelpful**; DeAlly
   preserves that status and links the finding to a pending correction.

Each gap can point back to the exact call, transcript line, and finding. The Solutions Lead can then
add or correct the knowledge so future analysis and answers handle the same topic more confidently.

---

## 6. Product highlights across modules

- **Calls** — call history and detail, a dual-stream live workspace, idempotent transcription uploads,
  rolling structured analysis, persistent evidence-linked findings, feedback, downloadable transcripts,
  and post-call summary/review pages.
- **Pipeline** — opportunities tracked through stages with a **list ↔ board** toggle, an **Owner**
  column, and **risk assessments** on every deal. The **New Deal** modal always attaches the deal to a
  real customer account.
- **Deal engagement log** — each deal page merges the deal's calls, the customer's proposals, and a
  permanent Activity trail (stage moves, lost reasons, notes) into one chronological log. Moving a
  deal to **Lost requires a reason**; an open deal on a Critical/High account is flagged **possible lost**.
- **Account health (risk tiers)** — every customer is scored **Critical / High / Medium / Low** from a
  mix of missed Service Reviews, proposals needing action, demand-account pipeline (vs. the
  configurable threshold), open deals, and inactivity. The used tier shows on the pipeline and customer pages,
  with an **at-risk panel** on the customer record.
- **Service Reviews** — recurring check-in schedules (cadence per account tier) that keep a rolling set
  of upcoming sessions, with reschedule (time-clash guarded), hold, cancel, catch-up, cadence changes,
  and graceful ending — never leaving a next-review gap.
- **Contacts** — multiple named contacts per customer with a single primary; adding a second contact
  from the same company never duplicates the customer account.
- **Proposals** — docs tied to opportunities and calls, archived per the retention policy; editable
  in place from the engagement log and the Proposals page.
- **Reporting** — team performance, per-account breakdowns, coaching review per call, and task progress.
- **Admin** — users (with roles/seats), roles, and teams; all scoped to the tenant. Removing a **sales
  agent** routes through a **reassignment plan** (suggested least-loaded owners, per-customer override,
  bulk-assign, approve); Team Leader/Solutions Lead seats get a fill-the-seat screen; other roles keep
  the inline Remove form.
- **Notifications** — a real notification system with per-item read toggling ("Mark as read" / green
  "Read" badge) and a read-all action.
- **Activity feed** — audit trail of system events (provisioning, transcript failures, etc.).

---

## 7. Architecture (a 2-minute version)

- **Laravel 13 / PHP 8.4** multi-tenant SaaS on the `kimwebi/saas-foundation` package (installed from GitHub).
- **Modules** — `modules/<Name>/{src,routes,resources/views}` with PSR-4 autoloading and a
  service provider per module (`Deally\Calls`, `Deally\Core`, `Deally\Pipeline`, etc.).
- **Multi-tenancy** — a central database holds users/tenants/memberships/subscriptions; each active
  tenant gets its **own SQLite database** (random `instance` key in `database/tenants/`). The
  `SetTenantContext` middleware binds the right connection before route-model binding hydrates models.
- **Frontend** — Blade + Vite/built assets, hand-rolled CSS (design tokens, light/dark theme with a
  one-click toggle), and vanilla JS. The live call uses `MediaRecorder` with independent microphone
  and meeting-audio queues; icons come from Bootstrap Icons.
- **Live AI backend** — `CallAssistant` providers, `AssistantFactory` driver resolution, server-only
  Groq/OpenAI credentials, strict structured analysis, defensive output validation, transcript and
  finding persistence, and linked knowledge-gap feedback.
- **Platform admin** — `admin/tenants` routes to create/clone tenant instances; CLI
  `php artisan deally:tenants:setup` provisions, migrates, and seeds tenants.

---

## 8. Suggested presentation walkthrough (demo script)

> All demo users log in with the password `password` (see `README.md`).

1. **Landing / login** — show the modern login, note the public **AI setup docs** link in the footer.
2. **Home dashboard** — the user lands in their workspace after login.
3. **Calls → open a call → Live** — choose the path being demonstrated:
   - with `LIVE_AI_DRIVER=dummy`, show the deterministic competitor, pricing, and knowledge-gap
     narrative;
   - with Groq/OpenAI configured, click **Start**, grant microphone permission, optionally share
     meeting audio, and show the independent **You** and **Meeting** source indicators.
4. **Watch the conversation become structured data** — show customer/agent transcript capture,
   source-linked signals, and knowledge-grounded **Say this / Ask this / Reference / Objection**
   cards in the Findings shelf.
5. **Improve a finding** — mark one **unhelpful** and show that the status is persisted and linked
   to a correction gap.
6. **Ask DeAlly** — try a pricing, comparison, security, or feature question; then flag an
   objection to show the knowledge-gap path.
7. **End and reopen the call** — end it and show that the final audio queues were drained, the call
   is completed, the findings are restored, and the transcript is available for review/download.
8. **Pipeline / account health** — flip **List ↔ Board**, open a deal to show the **engagement log**,
   then open the customer to show its **risk tier** and **Service Review** schedule; set up a review on
   a customer that lacks one.
9. **Knowledge Base / Solutions** — show the entries that ground every answer and the linked gap or
   correction work created during the call.
10. **Admin** — manage a user, role, or team; deactivate a sales agent to walk the **reassignment plan**
    (override, bulk-assign, approve); show tenant isolation.
11. **Reporting** — team performance + coaching view tying it all together.

---

## 9. Where to look

| Topic | File |
| --- | --- |
| Setup, demo accounts, modules table | `README.md` |
| Live AI / LLM internals, endpoints, config | `docs/ai-llm-integration.md` |
| Platform modules & code layout | `modules/*` (see `README.md` table) |

---

© 2026 Wyzone Labs. All rights reserved.