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
| The rep has to remember what the last call actually agreed | The review is a task, with correctable AI judgements and a deal-status gate |

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
| **Calls** | Schedule and invite a call, capture agent + meeting audio, transcribe both speakers, surface live findings, replay the recording, and turn the review into a task with real corrections. |
| **Pipeline** | Track opportunities with a list or board view, owner column, and per-deal risk flags. |
| **Customers** | Customer accounts with owners, multiple contacts (single primary), an at-risk panel, and a pre-filled Create Deal modal. |
| **Service Reviews** | Recurring per-customer check-in schedule with reschedule, hold, cancel, and catch-up. |
| **Tasks** | Personal/team follow-up tasks with one-click toggle; the per-call review task opens in a modal and cannot be closed while a deal-status flag is open. |
| **Proposals** | Build and edit proposals tied to calls, opportunities, and the knowledge base. |
| **Knowledge Base (KB)** | The single source of truth the AI answers from (pricing, features, processes); a searchable, paginated flashcard library with a one-card detail view. |
| **Reporting** | Team performance, account-level, coaching, and task reporting. |
| **Activity** | Audit-style activity feed of important system events. |
| **Notifications** | In-app call/lifecycle notifications with read state; pending AI gaps also surface in Workspace and the Solutions queue. |
| **Settings** | Profile/preferences, the demand-account threshold, and admin (users, roles, teams) for tenant owners. |
| **Tenant Management** | Platform admin: create, edit, and clone tenant instances. |
| **Docs** | Public AI setup documentation page. |
| **Legal** | Public Terms of Service (`/terms`) and Privacy Policy (`/privacy`) pages. Docs, Terms and Privacy are all reachable from the login footer. |

---

## 4. The flagship feature: live AI call assistant

This is the differentiator. DeAlly sits in the call with the agent: captured speech becomes a
durable transcript, and a lightweight analysis loop turns the latest context into evidence-linked
findings and knowledge-grounded guidance.

### How a live call works

1. **Start capture** — the agent clicks **Start** and grants microphone permission. The browser
   separately requests shared meeting audio; if sharing is declined, the call continues with the
   agent microphone alone.
2. **Two independent audio sources** — local microphone is labeled **agent**, and shared meeting
   audio is labeled **customer**. Each source is recorded and uploaded independently, so a slow
   transcription request never pauses the other recorder.
3. **Continuous server-side transcription** — `MediaRecorder` combines each source into 4-second
   chunks. Silent windows are dropped in the browser before upload, and DeAlly sends the rest to the
   configured provider, stores usable text as a `TranscriptLine`, and treats silence as a normal
   no-op rather than inventing a line. Each window is transcribed on its own, so a question that
   spans several windows — or a long pause in the middle — is never cut off at the chunk boundary.
4. **Structured conversation analysis** — after any chunk, whichever side spoke, a throttled
   rolling-window request (eight recent lines by default, no more often than every 10 seconds per
   call) detects buying signals, intent, objections, competitors, deal risk, and knowledge gaps.
5. **Knowledge-grounded recommendations** — the same analysis returns short **say**, **ask**,
   **reference**, or **objection** guidance grounded in the tenant's Knowledge Base.
6. **A one-line paraphrase of what was just said** — the stream's **Heard** card, capped at about a
   dozen words and never in quotation marks, because a card labelled as a summary that is actually a
   quote is worse than no card.
7. **Persistent Findings shelf** — accepted signals and recommendations are validated again on the
   server, deduplicated, linked to a stored transcript line, and rendered as cards. Card roles are
   distinguished by **edge treatment** (solid, dashed, none, dotted, red rule) rather than by colour
   alone, and the shelf scrolls rather than clipping a card mid-sentence. Reopening the call restores
   the shelf immediately.
8. **Feedback improves the workflow** — the agent can mark a persisted finding **unhelpful**. That
   status is stored and a linked correction gap enters the Solutions queue.
9. **Ask or flag on demand** — the agent can ask DeAlly a free-form question or flag an objection;
   both use the recent transcript and Knowledge Base context. The Ask box is an auto-growing
   textarea that accepts up to 4,000 characters (**Enter** sends, **Shift+Enter** starts a new line),
   so a long question is typed, pasted or read back in full. Missing answers become knowledge
   gaps rather than unsupported claims.
10. **End without losing the tail** — End Call stops both recorders, flushes their final chunks,
    waits for both upload queues, then completes the call and opens its summary/review flow.

```text
Agent mic ──▶ 4s agent chunks ───┐
                                ├─▶ server transcription ─▶ transcript lines
Meeting audio ─▶ 4s customer chunks ┘                         │
                                                               ▼
                                            throttled structured analysis
                              signals · spoken recommendations · heard
                                                               │
                              persisted findings + ephemerals ◀─┘
                                     │               │
                            agent feedback     knowledge gaps
```

The browser submits audio only to DeAlly. Provider credentials, endpoints, speech-to-text calls,
LLM calls, and response validation remain on the Laravel server.

### The customer panel beside the call

Read-only, and fixed for the duration of the call. It shows real context — the contact and their
role, the deal's stage and value, proposed packages, a sentiment trend computed from actual stored
calls, unresolved commitments from the knowledge-gap queue, and the call history — and every section
says so plainly when it has nothing. A single previous call is never called a trend.

It contains no controls. Every agent control lives in the AI panel, so a control can never be found
in the wrong place.

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

## 5. The call lifecycle: initiation, live, review

A call is a chain, and the weakest link decides whether the chain is worth anything. DeAlly's
initiation and review flow is built around one principle: **a rep must never be told something that
did not happen.**

### Booking it

The platform picker offers only the platforms this company has turned on, and each says plainly
whether it is connected. A session type (`discovery`, `service_review`, `follow_up`) is recorded and
reported on but never branched on — a Service Review session follows exactly the same start,
guidance and review as any other call, because giving one call type different behaviour would make
it unpredictable for the rep.

The invitation is real. It is composed from the call, sent through Laravel Mail, and stored verbatim
on the call, so what was actually sent stays answerable. When no meeting exists yet, the invitation
says the joining details will follow instead of including a link that goes nowhere. A failed delivery
is recorded on both the invitation and the call, with the transport error.

### When nothing goes right

A call that failed or went to a no-show is recorded with its reason and raises a **Reschedule**
task — otherwise a missed call silently leaves the pipeline with a dead deal and no reminder. The
no-show survives a reschedule: moving the calendar entry is not the same as the missed call never
happening.

### Meeting platforms: the flow is real, the connectors are not shipped

**No meeting platform is connected**, and the app says so rather than pretending otherwise. The
picker, the data model, the states, the UI and the tests are all real. The provider calls are not
implemented, because a Zoom Server-to-Server OAuth app, a Teams bot registration and a Google
service-account joiner all need credentials that cannot be simulated.

The alternative — a connector returning a well-formed-looking join URL — is worse than having
nothing at all, because a rep will believe the customer has the link. So a join URL is only ever
written from a provider response, and the transcription bot is only ever reported as joined when the
provider confirmed it. Every other outcome is stored on the call with a plain-language reason, and
the live-call header shows the platform's current state while the call is running.

Adding a connector is a documented five-step job: credentials in `config/services.php`, the
`MeetingPlatformConnector` contract, a `register()` call, and letting `isConnected()` drive the
flag.

### The post-call summary is deliberately sparse

It carries the call's real readiness, any open deal-status flags, whether a proposal was agreed and
in what terms, and a way into the review task. It is a handover, not the review — the review is
work, and work is a task.

**Create Proposal** appears only when the analysis detected proposal intent. An unconditional button
on every call is a suggestion the rep has to evaluate and discard rather than an action.

### The review task is the real review

It opens in a modal from the tasks list, so a rep can work through their queue without losing their
place, and it points at the call it is actually about rather than the most recent call for that
company. It carries what was heard, the objections the model detected kept apart from the ones the
rep added during review, the actions that were never taken, correctable sentiment and readiness, the
log of everything already corrected, and the open flags. It also shows the **Agent Performance**
score-card — talk ratio, objections handled, AI suggestions and how many were marked useful — the same
numbers the Coaching Review derives for that call, so a rep reviewing their own task sees the same
snapshot a manager reviewing them sees.

**It cannot be closed while a deal-status flag is open** — from the modal or from the list, both of
which say why. A flag that does not block anything is a note.

Correcting a judgement never overwrites the model's. `ai_sentiment` and `ai_readiness` keep what the
AI believed, the effective columns carry the agent's version, and the difference is stored in
`call_corrections`. That difference is the only part of a correction worth learning from.

### Replay

Every captured window is written to the private disk *before* transcription is attempted, so the
review can play the call back — play, pause, seek, and a filter for one source or both — with a
`▶` on every transcript line that jumps to the moment it was said. Findings are grouped under the
line that triggered them, so the transcript reads as an exchange.

### Nothing is thrown away

"Clear the findings" hides cards; it does not delete rows. Every card in the live stream is written
to the call's permanent record before the response returns, so the review can show what the AI was
attending to at each moment. Audio is never pruned automatically either: deleting a recording is not
something the app should do unasked.

See `docs/call-lifecycle.md` for the data model, the endpoints, and how to add a platform connector.

---

## 6. Knowledge gaps — feedback loop that gets smarter every call

DeAlly turns three kinds of uncertainty into actionable Solutions work:

1. **Automatic gap detection** — a chunk produces a validated `knowledge_gap` signal when the
   requested answer is not supported by the Knowledge Base.
2. **Objection follow-up** — when the rep flags an objection and the assistant has no documented
   response, DeAlly records the objection and returns a clarifying prompt. An objection added during
   review is recorded too, and kept apart from the ones the model caught: the second kind is the
   expensive one, because it is the model missing something.
3. **Unhelpful finding feedback** — a rep can mark a persisted live finding **unhelpful**; DeAlly
   preserves that status and links the finding to a pending correction.

Each gap can point back to the exact call, transcript line, and finding. The Solutions Lead can then
add or correct the knowledge so future analysis and answers handle the same topic more confidently.

**The queue never holds the same question twice.** A pending gap is keyed by its trimmed,
case-insensitive text, so a repeated objection — or the same missing answer surfacing on several
calls — is not re-added to the Solutions queue. Resolving one instance (approve, reject, or any
non-edit action) also retires the sibling pending gaps for the same question, so an answer is never
approved into the knowledge base (and never notified about) several times.

---

## 7. Product highlights across modules

- **Calls** — scheduling and invitations, a dual-stream live workspace, idempotent transcription
  uploads, rolling structured analysis, a persistent evidence-linked findings shelf, a kept audio
  recording with per-line replay, the ephemeral stream, and a post-call summary that hands off to a
  review task rather than pretending to be the review.
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
  of upcoming sessions, with reschedule (time-clash guarded), hold, cancel, catch-up, cadence changes
  that apply **to sessions scheduled from now on only** (existing upcoming reviews are never rewritten),
  and graceful ending — never leaving a next-review gap.
- **Contacts** — multiple named contacts per customer with a single primary; adding a second contact
  from the same company never duplicates the customer account.
- **Proposals** — docs tied to opportunities and calls, archived per the retention policy; editable
  in place from the engagement log and the Proposals page.
- **Knowledge Base** — a searchable, paginated flashcard library. Entries are served 12 at a time,
  ordered by type then title; a search box filters by title, description or type with the query kept
  across pages, and clicking any card opens a one-card detail view.
- **Reporting** — team performance, per-account breakdowns, coaching review per call, and task progress.
- **Admin** — users (with roles/seats), roles, and teams; all scoped to the tenant. Removing a **sales
  agent** routes through a **reassignment plan** (suggested least-loaded owners, per-customer override,
  bulk-assign, approve); Team Leader/Solutions Lead seats get a fill-the-seat screen; other roles keep
  the inline Remove form.
- **Notifications** — a real notification system with per-item read toggling ("Mark as read" / green
  "Read" badge) and a read-all action. Which notification categories the company receives (Expert
  Answers queue, call reports) is decided **at the company level** in Settings by account managers —
  never per person.
- **Activity feed** — audit trail of system events (provisioning, transcript failures, etc.). Each
  call also carries its own activity history in its detail modal, so the rep who triggered a provider
  error can see it from the call itself without needing the tenant-wide feed permissions.

---

## 8. Product guarantees — the non-negotiables

Every product decision below is load-bearing: the app is engineered so it cannot get out of these,
and the test suite (plus `docs/ai-llm-integration.md` and `docs/call-lifecycle.md`) documents how each
is enforced.

1. **AI answers come from the knowledge base.** Research suggestions are grounded in
   `KnowledgeEntry` rows (type + model gating in `LiveAssistant`); an unanswered request becomes a
   `KnowledgeGap`, never a made-up answer. Without a knowledge package the assistant says so
   (`NO_KNOWLEDGE_PACKAGE`).
2. **The knowledge base is written only through governed paths.** Rows are created solely from the
   Solutions queue (`deally.kb.manage`) via approve-into-KB, and the KB edit surface is restricted on
   the knowledge base page. The live assistant can record a gap; it cannot write a KB row.
3. **Nothing is deleted.** Tasks archive (status → `closed`, row stays), closing a call keeps its
   transcript and audio, "clear the findings" hides cards without deleting rows, and ending a service
   review removes only future placeholder sessions. The legacy wholesale-transcript-rewrite endpoint
   was removed rather than allowed to bulk-delete lines.
4. **Consent is by invitation.** Recording begins only for a joined call; the deliberate-consent
   vehicle is the real invitation the rep sends (recorded verbatim on the call), not a checkbox in
   the app. This is a policy decision, not a code path.
5. **The live page shows cards, never a raw transcript.** The screen renders findings cards, heard
   lines and prompts; the conversation text stays in the review.
6. **The findings shelf is read-only; interaction lives in the AI panel.** Reps engage through the
   ask / objection / hero buttons and the dot-feedback on cards (`live-call.js`) — no inline editing
   of what the assistant produced.
7. **The assistant announces its state.** An idle call shows "DeAlly is listening"; when the
   assistant asks the rep a question it brings the hero up ("AI Asks You").
8. **A reassignment plan changes nothing until approved.** `ReassignmentService` builds a draft only;
   `UserController::approvePlan` applies the transfers and removes the membership.
9. **DeAlly steers; it does not echo.** Noticed frames are triggered by *customer* lines; analysis
   of agent lines produces steering recommendations (say/ask/reference), never a parrot of the rep.
10. **Notifications are decided at the company level.** Notification categories (Expert Answers
    queue, call reports) are per-tenant toggles in Settings, editable only by account managers; an
    off category emits nothing for anyone.
11. **Retention is tier-driven and platform-controlled.** Closed-deal transcripts and proposals
    archive by the tenant's retention window; only DeAlly Platform Support can change the tier, and
    the UI says so.
12. **Errors are visible to the person who triggered them.** Activity rows (`Activity`) — including
    provider failures — are written with the call as subject and shown on the call's detail modal, so
    an agent does not need the tenant-wide feed permission to see their own errors.
13. **Deal-status flags close only inside review surfaces.** Flags resolve from the review-task
    modal or the full review; the "possible lost" marker on a deal is read-only and links to the
    tasks that can clear it.
14. **Paraphrases are tight and never quoted.** Heard lines are capped at 12 words, present tense,
    no quotation marks, and normalized server-side (`normalizeParaphrase`).
15. **Ending a call always creates the review task.** `CallController::end()` unconditionally
    `firstOrCreate`s "Review Call — {company}" due within 24 hours, so no call ends without a
    follow-up.
16. **The review task shows the agent performance score.** The same talk-ratio / objections-handled
    / suggestions / usefulness numbers the Coaching Review shows for any rep appear in the review
    task — one computation (`CallReviewBrief::agentPerformance()`), one mirror.
17. **Missed sessions move risk; deal flags move deals.** `RiskService` marks a customer Critical
    and `at_risk` when a Service Review session is missed, separate from the call-level deal-risk
    flag, and clears it once the session is held.
18. **Cadence changes apply to future sessions only.** Changing a review cadence edits the
    schedule's going-forward interval; already-scheduled upcoming reviews are kept exactly where they
    are — nothing retroactive is rewritten.
19. **A task is todo or closed — nothing else.** `scopeOverdue` is computed from
    `due_at < now()` over the todo set; there is no "done at some point" limbo.
20. **Every task carries an explicit due time.** A due *moment* is required — a calendar date
    without a time is rejected with an explanation, on the Tasks form and the home quick-add alike.

---

## 9. Architecture (a 2-minute version)

- **Laravel 13 / PHP 8.4** multi-tenant SaaS on the `kimwebi/saas-foundation` package (installed from GitHub).
- **Modules** — `modules/<Name>/{src,routes,resources/views}` with PSR-4 autoloading and a
  service provider per module (`Deally\Calls`, `Deally\Core`, `Deally\Pipeline`, etc.).
- **Multi-tenancy** — a central database holds users/tenants/memberships/subscriptions; each active
  tenant gets its **own SQLite database** (random `instance` key in `database/tenants/`). The
  `SetTenantContext` middleware binds the right connection before route-model binding hydrates models.
  Tenant tables therefore cannot declare foreign keys to central tables such as `users` — a user id on
  a tenant table is an indexed column.
- **Frontend** — Blade + Vite/built assets, hand-rolled CSS (design tokens, light/dark theme with a
  one-click toggle), and vanilla JS. The live call uses `MediaRecorder` with independent microphone
  and meeting-audio queues; icons come from Bootstrap Icons.
- **Live AI backend** — `CallAssistant` providers, `AssistantFactory` driver resolution, server-only
  Groq/OpenAI credentials, strict structured analysis, defensive output validation, transcript and
  finding persistence, and linked knowledge-gap feedback.
- **Meeting platforms** — a `MeetingPlatformConnector` contract plus a manager. No connector is
  registered, so every platform reports itself unavailable with a reason.
- **Platform admin** — `admin/tenants` routes to create/clone tenant instances; CLI
  `php artisan deally:tenants:setup` provisions, migrates, and seeds tenants.

---

## 10. Suggested presentation walkthrough (demo script)

> All demo users log in with the password `password` (see `README.md`).

1. **Landing / login** — show the modern login, note the public **Docs**, **Terms** and **Privacy**
   links in the footer.
2. **Home dashboard** — the user lands in their workspace after login.
3. **Calls → schedule a call** — pick a platform, a session type, and a date. Read out that the
   platform says it is enabled but not connected, and that the invitation says the joining details
   will follow. **This is the honesty demonstration**: the alternative was a made-up join link, and a
   rep who believes the customer has the link is worse off than one who knows it does not exist.
4. **Open a call → Live** — choose the path being demonstrated:
   - with `LIVE_AI_DRIVER=dummy`, show the deterministic competitor, pricing, and knowledge-gap
     narrative;
   - with Groq/OpenAI configured, click **Start**, grant microphone permission, optionally share
     meeting audio, and show the independent **You** and **Meeting** source indicators.
5. **Watch the conversation become structured data** — show customer/agent transcript capture,
   source-linked signals, and knowledge-grounded **Say this / Ask this / Reference / Objection**
   cards in the Findings shelf, distinguished by edge treatment.
6. **Improve a finding** — mark one **unhelpful** and show that the status is persisted and linked
   to a correction gap.
7. **Ask DeAlly** — try a pricing, comparison, security, or feature question; then flag an
   objection to show the knowledge-gap path.
8. **End and reopen the call** — end it and show that the final audio queues were drained, the call
   is completed, and the summary is deliberately short: readiness, flags, proposal intent, and a way
   into the review task.
9. **Work the review task** — open it from the Tasks list, show the replay bar, jump to a line with
   `▶`, correct the sentiment and show that the AI's original read is still kept, add an objection the
   model missed, raise a flag, and try to close the task — it refuses, and says why.
10. **Pipeline / account health** — flip **List ↔ Board**, open a deal to show the **engagement log**,
    then open the customer to show its **risk tier** and **Service Review** schedule; set up a review on
    a customer that lacks one.
11. **Knowledge Base / Solutions** — browse the searchable, paginated flashcards, run a keyword
    search, open a card to view it in full, then show the gap or correction work the call created.
12. **Admin** — manage a user, role, or team; deactivate a sales agent to walk the **reassignment plan**
    (override, bulk-assign, approve); show tenant isolation.
13. **Reporting** — team performance + coaching view tying it all together.

---

## 11. Where to look

| Topic | File |
| --- | --- |
| Setup, demo accounts, modules table | `README.md` |
| Call initiation, live surfaces, review task, meeting platforms | `docs/call-lifecycle.md` |
| Live AI / LLM internals, endpoints, config | `docs/ai-llm-integration.md` |
| Platform modules & code layout | `modules/*` (see `README.md` table) |

---

© 2026 Wyzone Labs. All rights reserved.
