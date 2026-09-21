# DeAlly — System Overview & Product Presentation

> **One-sentence pitch:** DeAlly is an AI-powered sales enablement platform that listens to your sales
> calls in real time, suggests what to say next, and turns every conversation into repeatable
> pipeline, proposals, and playbooks.

---

## 1. What is DeAlly?

DeAlly is a multi-tenant SaaS application for modern sales teams. It combines classic
CRM/sales tools (calls, pipeline, tasks, proposals) with a **live AI assistant** that works beside
the agent during the call — transcribing what the customer says, pulling grounded answers from the
company's knowledge base, and feeding the agent the exact words to say.

The name says it all: the system is a **sales ally** — always listening, always ready with the next
best thing to say.

### The problem it solves

| Without DeAlly | With DeAlly |
| --- | --- |
| Agent improvises responses during calls | Agent reads AI-suggested, knowledge-grounded replies live |
| Customers repeat questions the team already answered elsewhere | Every answer draws on a shared knowledge base and past calls |
| Calls are a black box ("what did they actually object to?") | Transcripts, objection flags, and sentiment are captured automatically |
| Winning talk tracks live inside one rep's head | Best answers become reusable playbook cards and knowledge entries |
| Manual note-taking takes attention away from the conversation | Transcription, summaries, and action items are automatic |

---

## 2. Who is it for?

- **Sales reps / agents** — live script assistance, instant answers, objection handling.
- **Sales leadership / managers** — team performance, coaching review, pipeline and proposal reporting.
- **Solutions teams** — own the knowledge base that grounds every AI answer; get notified when a question isn't covered (knowledge gaps).
- **Tenant owners** — manage users, roles, teams, and data retention from a single control plane.

---

## 3. Platform at a glance

DeAlly is modular. Each area of the product is its own module under `modules/`, so capabilities can
be built, tested, and shipped independently.

| Area | What it does |
| --- | --- |
| **Home (Workspace)** | Landing dashboard for the signed-in user. |
| **Calls** | Log calls, see histories/summaries, run a live agent-assisted call, review transcripts. |
| **Pipeline** | Track opportunities through the sales pipeline. |
| **Tasks** | Personal/team follow-up tasks with one-click toggle. |
| **Proposals** | Build proposals tied to calls, opportunities, and the knowledge base. |
| **Knowledge Base (KB)** | The single source of truth the AI answers from (pricing, features, processes). |
| **Reporting** | Team performance, account-level, coaching, and task reporting. |
| **Activity** | Audit-style activity feed of important system events. |
| **Notifications** | In-app notifications (new calls, read/unread state, AI knowledge gaps). |
| **Settings** | Profile/preferences, and admin (users, roles, teams) for tenant owners. |
| **Tenant Management** | Platform admin: create, edit, and clone tenant instances. |
| **Docs** | Public AI setup documentation page (also reachable from the login footer). |

---

## 4. The flagship feature: live AI call assistant

This is the differentiator. DeAlly sits in the call with the agent.

### How a live call works

1. **Start the call** — agent opens the live call screen and clicks **Start**. The mic is now live.
2. **Continuous transcription** — the browser captures short audio chunks (~6s); each chunk is sent
   to the AI, which transcribes it (Whisper) into a `transcript line` stored on the call.
3. **Live suggestions** — for every customer line, the AI generates **two suggested replies**,
   grounded in the knowledge base, and renders them as cards in the **Findings** panel.
4. **Ask DeAlly anything** — the agent can type an ad-hoc question (*"what does our plan include?"*)
   and get a plain-English, KB-grounded answer to read to the customer.
5. **Objection handling** — the agent can flag an objection happening on the call; if the AI can't
   answer with confidence it opens a **knowledge gap**, alerts the Solutions Lead, and tells the
   agent exactly what to ask the customer so the gap gets closed next time.
6. **End the call** — press End Call (or `Ctrl/Cmd + Enter`); the review and summary pages are ready
   from the captured transcript and cards.

```
Agent talks ──▶ mic chunk ──▶ transcribe (Whisper) ──▶ transcripts + suggestions
                                                   ──▶ KB-grounded cards in Findings panel
                                                   ──▶ agent reads them to the customer
```

### AI drivers

- **LiveAssistant (OpenAI)** — real transcription + chat completions, used when `OPENAI_API_KEY` is set.
- **DummyAssistant** — a deterministic fallback with no network, so the whole flow always runs in
  demos, CI, and tests.

See `docs/ai-llm-integration.md` for endpoints, config, and how to swap providers.

### Where suggestions come from

Every Knowledge Base entry is injected into the AI prompts. This is what keeps answers honest —
**the AI never invents facts**; it grounds every reply in KB entries and the live transcript.
A well-maintained KB makes the assistant visibly smarter in every call.

---

## 5. Knowledge gaps — feedback loop that gets smarter every call

When the AI meets an objection or question it can't answer confidently, DeAlly:

1. Creates a `KnowledgeGap` (status `pending`).
2. Notifies the Solutions Lead.
3. Tells the agent what to ask the customer on the spot.

The lead then adds the answer to the knowledge base — closing the loop so future calls handle the
same objection automatically.

---

## 6. Product highlights across modules

- **Calls** — list of calls with detail, live agent screen, review page with transcript + sentiment,
  and auto-generated summaries.
- **Pipeline** — opportunities tracked through stages, complementing the call history.
- **Proposals** — docs tied to opportunities and calls, archived per the retention policy.
- **Reporting** — team performance, per-account breakdowns, coaching review per call, and task progress.
- **Admin** — users (with roles/seats), roles, and teams; all scoped to the tenant.
- **Notifications** — a real notification system with per-item read toggling ("Mark as read" / green
  "Read" badge) and a read-all action.
- **Activity feed** — audit trail of system events (provisioning, transcript failures, etc.).

---

## 7. Architecture (a 2-minute version)

- **Laravel 13 / PHP 8.4** multi-tenant SaaS on the `multitenant/saas-foundation` package.
- **Modules** — `modules/<Name>/{src,routes,resources/views}` with PSR-4 autoloading and a
  service provider per module (`Deally\Calls`, `Deally\Core`, `Deally\Pipeline`, etc.).
- **Multi-tenancy** — a central database holds users/tenants/memberships/subscriptions; each active
  tenant gets its **own SQLite database** (random `instance` key in `database/tenants/`). The
  `SetTenantContext` middleware binds the right connection before route-model binding hydrates models.
- **Frontend** — Blade + Vite/built assets, hand-rolled CSS (design tokens, light/dark theme with a
  one-click toggle) and vanilla JS. Icons via Bootstrap Icons.
- **Platform admin** — `admin/tenants` routes to create/clone tenant instances; CLI
  `php artisan deally:tenants:setup` provisions, migrates, and seeds tenants.

---

## 8. Suggested presentation walkthrough (demo script)

> All demo users log in with the password `password` (see `README.md`).

1. **Landing / login** — show the modern login, note the public **AI setup docs** link in the footer.
2. **Home dashboard** — the user lands in their workspace after login.
3. **Calls → open a call → Live** — start the mic, speak a couple of sentences, and watch:
   - the transcript fill in,
   - "Heard" cards appear,
   - **suggested replies** render in the Findings panel (with a real key: genuine AI; without: the
     deterministic demo driver keeps the show alive).
4. **Ask DeAlly a question** in the live pane (*"pricing, comparison, feature check"*).
5. **Flag an objection** — show the knowledge-gap notification to the lead's inbox.
6. **Review / Summary** — reopen the same call and show the recorded transcript and card history.
7. **Knowledge Base** — show the entries; explain they power every AI answer.
8. **Admin** — manage a user, role, or team; show tenant isolation.
9. **Reporting** — team performance + coaching view tying it all together.

---

## 9. Where to look

| Topic | File |
| --- | --- |
| Setup, demo accounts, modules table | `README.md` |
| Live AI / LLM internals, endpoints, config | `docs/ai-llm-integration.md` |
| Platform modules & code layout | `modules/*` (see `README.md` table) |