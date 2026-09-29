# DeAlly

DeAlly is a multi-tenant SaaS application for sales teams to track calls, pipeline, tasks, and proposals, with a server-integrated live AI assistant for live transcription and knowledge-grounded call guidance. It is built on Laravel 13 and the `kimwebi/saas-foundation` package (installed from GitHub), with feature modules under `modules/`.

## Architecture

The application is split into feature modules, each with its own `routes/`, `resources/views/`, and `src/` directory. Modules are autoloaded via PSR-4 in `composer.json` and registered through each module's service provider.

| Module | Namespace | Routes prefix | Views namespace | Responsibilities |
| --- | --- | --- | --- | --- |
| `Core` | `Deally\Core` | — | `core::` | Shared layouts, guest login, tenant binding middleware |
| `Calls` | `Deally\Calls` | `/app/calls` | `calls::` | Call initiation, dual-stream live AI capture, ephemeral stream, replay, review, corrections, and post-call summary |
| `Pipeline` | `Deally\Pipeline` | `/app/pipeline` | `pipeline::` | Sales pipeline (list/board), customer accounts & contacts, deals, risk tiers, service reviews |
| `Tasks` | `Deally\Tasks` | `/app/tasks` | `tasks::` | Task management, including the per-call review task and its deal-status gate |
| `Proposals` | `Deally\Proposals` | `/app/proposals` | `proposals::` | Proposals and knowledge base |
| `Solutions` | `Deally\Solutions` | `/app/solutions` | `solutions::` | Solutions lead: gap queue, corrections, VOC trends |
| `Settings` | `Deally\Settings` | `/app/account` | `settings::` | User account settings, roles, teams, users, integrations |
| `Workspace` | `Deally\Workspace` | `/app/home` | `workspace::` | Workspace dashboard |

### Multi-tenancy

- The central database holds users, tenants, memberships, subscriptions, and teams (`teams` and `team_user` live alongside the central `users` and `tenants`, with cascade foreign keys so deleting a user or tenant automatically removes their team memberships).
- Each active tenant gets its own SQLite database under `database/tenants/` with a random `instance` key.
- Tenant management (central CRUD, provisioning commands, tenant-scoped users/invitations/roles/domains/settings, instance switching, the sole system-owner `superadmin` flag) lives in the `kimwebi/saas-foundation` package, installed from GitHub as `composer require kimwebi/saas-foundation`. The package's central console also ships the **Setup Console** and the platform-wide **Audit Log**, gated by a `platform.operator` middleware that admits the super-admin or any user carrying the `is_platform_support` flag.
- Module routes run under the `deally` middleware group registered in `bootstrap/app.php`, which applies the session, CSRF, `auth`, and `tenant.context` middleware plus route-model binding substitution. `tenant.context` (`SetTenantContext`) runs before `SubstituteBindings` so route-bound models hydrate from the right tenant connection.

### Registration

Modules are registered in `bootstrap/providers.php` via their service providers and PSR-4 autoloaded in `composer.json`:

- `Deally\Calls\` → `modules/Calls/src/`
- `Deally\Core\` → `modules/Core/src/`
- `Deally\Pipeline\` → `modules/Pipeline/src/`
- `Deally\Proposals\` → `modules/Proposals/src/`
- `Deally\Settings\` → `modules/Settings/src/`
- `Deally\Solutions\` → `modules/Solutions/src/`
- `Deally\Tasks\` → `modules/Tasks/src/`
- `Deally\Workspace\` → `modules/Workspace/src/`

Views use the module namespace (`calls::pages.calls`) and extend shared layouts from `core::layouts` and `core::partials`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Syncing package-only changes (`kimwebi/saas-foundation`)

The `kimwebi/saas-foundation` package is installed from a GitHub `vcs` repository pinned to `dev-main`, so the app runs from a git clone at `vendor/kimwebi/saas-foundation`. The package source of truth is its own repo (`github.com/kimwebi/saas-foundation`, local checkout at `C:\Users\Kimson\Downloads\saas-foundation`). After changing the package, push the changes there, then pull them into the app:

```bash
# 1. Pull the latest package source into vendor/ (git clones dev-main)
composer update kimwebi/saas-foundation

# 2. Re-publish the package's CSS/assets into public/vendor/...
php artisan vendor:publish --provider="SaasFoundation\Providers\SaasFoundationServiceProvider" --tag=saas-assets --force

# 3. Only if the package added a new migration:
php artisan migrate

# 4. Only if a rendered view still looks stale:
php artisan view:clear
```

Notes:

- **Blade views** are loaded straight from the cloned package (`loadViewsFrom`), so the composer update in step 1 is enough for template changes.
- **CSS and other assets** are *published* copies: the source lives in the package's `resources/css/app.css` and is copied to `public/vendor/kimwebi/saas-foundation/css/` by step 2 — skipping it means the old look keeps serving.
- **New migrations** in the package's `database/migrations/` are auto-discovered after the update and run with the app's regular `php artisan migrate`.
- The repo has no release tags yet, so the lock pins `dev-main` and a `preferred-install` override forces a git source clone (the GitHub zipball endpoint 404s for an untagged repo).

### Provisioning demo tenants

Run the central migrations first, then the tenant setup command. The command seeds the central demo tenants, users, and roles itself, and then creates, migrates, and seeds each tenant database against those real owners — so on a fresh checkout it just works, in order:

```bash
php artisan migrate
php artisan deally:tenants:setup
php artisan db:seed
```

- `php artisan migrate` applies the central schema.
- `php artisan deally:tenants:setup` seeds the central demo data (tenants, users, roles, access via `DemoSeeder` + `DeallyAccessSeeder`) and then creates + seeds the tenant databases. Tenant records get correct owners because the central users already exist. Idempotent, so re-running only re-seeds.
- `php artisan db:seed` runs `DatabaseSeeder` afterwards; it is idempotent and additionally creates the platform super-admin.

Use `--tenant=<uuid|instance>` to provision a single tenant and `--fresh` to rebuild a tenant database:

```bash
php artisan deally:tenants:setup --fresh
```

Tenant-specific tables live in `database/migrations/tenant/` (customers, contacts, calls and transcripts, live-call findings, call recordings and queries, call ephemerals, corrections, flags and invitations, meeting platforms, service reviews, account settings, proposals, deal ownership, and `tasks.call_id`). To apply *pending* tenant migrations to already-provisioned dev tenant databases without rebuilding them, run `php artisan tenant:migrate`.

### Demo accounts

All demo users log in with the password `password`:

| Email | Acme Corp | Globex |
| --- | --- | --- |
| `alice@example.com` | owner | owner |
| `bob@example.com` | admin | admin |
| `charlie@example.com` | sales-agent · viewer | viewer |
| `david@example.com` | admin | solutions-lead |
| `erica@example.com` | team-leader | — |
| `support@example.com` | viewer | — |

> Alice is **the** tenant owner — she owns every demo instance (Acme Corp and Globex). The other members carry per-instance roles, which is what exercises multitenancy: Bob is an administrator in both instances; Charlie is a sales agent in Acme and a read-only viewer in Globex; David is an administrator in Acme and Globex's solution lead; Erica leads the Acme East Pod team. Erica's and David's accounts are seeded by `DeallyAccessSeeder` (`Erica Valdez` teammates land in the East Pod, watching the team workspace, team pages and team reporting). Stephanie Orr (`support@example.com`) is platform support: she carries the `is_platform_support` flag on her central user record (she is *not* a tenant role, so nothing platform-related shows up inside a customer's instance) and can open the **Setup Console** and the platform-wide **Audit Log** in the `kimwebi/saas-foundation` central console. A tenant owner can change any member's role from the Administration → Users page.

## Roles & access (access matrix)

The seeded roles implement the following account access. "Sees" is what a seat can read, "Owns" is what it can change. Everything is enforced by permissions seeded in `DeallyAccessSeeder` and seat scoping in `Deally\Core\Services\Seat` (sales agents are scoped to their own user id, team leaders to their team's member ids, owners/admins/solutions-leads see everything in scope). Platform support is **not** a tenant role: it is the `is_platform_support` flag on the central user record, which admits the flagged user to the package's platform consoles (setup console and platform-wide audit log) without granting any tenant-data permissions.

| Role | Sees | Owns |
| --- | --- | --- |
| **Sales Agent** | Only their own Customers/Deals, Tasks, Calls, Proposals and their own pipeline | Their own pipeline, tasks, calls and proposals |
| **Team Leader** | Every such record of the agents on their team (read-only for anyone outside), team health, approvals | Team health, approvals, coaching, reassignment (scope = managed agents) |
| **Solutions Lead** | The org-wide Knowledge Base, gap queue, corrections queue, and VOC trends — what the AI is allowed to say (org-wide, not tied to a team, customer or deal) | KB governance: approving/editing/rejecting gap answers and corrections |
| **Tenant Admin / Owner** | Team structure, integrations, account settings | Account-level configuration (teams, users, roles, integrations, settings) |
| **Platform Support** *(flag, not a role)* | Setup console, platform-wide audit logs, central dashboard companions | Provisioning new customers (tenant databases) |

Implementation notes:

- **Sales Agent / Team Leader / Tenant Admin** seat scoping lives in `Seat::userIds()`: `null` means every record in scope (owner, admin, solutions-lead), a team leader sees team member ids + self, a sales agent only themselves.
- **VOC trends** (Solutions Lead) is a data-driven pane on the Solutions Lead page (`deally.kb.manage`) that aggregates call sentiment across the whole tenant into a 6-month heatmap plus a 30-day positive/negative summary.
- **Integrations** (Tenant Admin / Owner) is under Administration → Integrations (`deally.integrations.view/manage`), storing per-tenant toggles in the `tenants.settings` JSON column.
- **Setup Console & platform-wide Audit Log** (Platform Support + superadmin) live in the `kimwebi/saas-foundation` package as `central.setup.*` / `central.audit.index`, under the Platform Ops section of the package's central console, gated by the `platform.operator` middleware (`SaasFoundation\Http\Middleware\EnsurePlatformOperator`, backed by `User::isPlatformOperator()` — super-admin or `is_platform_support` flag). The console lists every customer with provisioning status, provisions tenant databases, and can create a new customer and provision its database in one action. The app binds the package's `TenantProvisioner` to `DeallyTenantProvisioner` (`CoreServiceProvider`), so provisioning from the console uses DeAlly's tenant migrations and seeder. The DeAlly sidebar's Platform group links into the package console (`central.setup.index`). Platform support is a plain boolean on the `users` table (`is_platform_support`) seeded through `DeallyAccessSeeder`'s demo roster — there is no `platform-support` role.
- The package's central console ships its own black/grey/red/dark-blue theme (`resources/css/app.css`, published to `public/vendor/kimwebi/saas-foundation/css`): a soft-dark canvas (`#14171c`), surfaces at `#1c2027`, borders at `#2e343d`, dark-blue primary accents and red for danger — IBM Plex Sans for body type and IBM Plex Mono for all numerics (stat values, chart totals, pagination). A light/dark **theme toggle** lives in the console topbar and on the auth pages (persisted in `localStorage`, defaulting to dark). The dashboard and setup console use compact stat cards (blue/grey/red icon chips), a pure-CSS donut chart for the customer-status mix, a bar graph by status, and a **customer milestone** — a blue progress bar toward `CentralDashboardController::MILESTONE_TARGET` (10 customers), switching to a milestone-reached panel once the target is reached. The sidebar uses Bootstrap Icons on a deep charcoal surface.
- The platform-level **Super Admin** (`tech@wyzone.com`, a flag set in `DatabaseSeeder`) bypasses all permission checks and can reach the setup console and the package's central console (`central.*`).

## Roles, calls and task assignment

- The Administration → Roles page lists only the roles in use in the active instance (a **global** role appears when a member of this instance holds it, e.g. Tenant Owner, Viewer, Sales Agent, Team Leader), plus any roles created for this instance. The platform-level Super Administrator and unused global roles are never listed. Member counts are scoped to the active instance.
- Owners and admins can open the Roles, Teams and Users administration pages.
- **Reporting**: the Team Tasks page is seat-scoped — sales agents see only their own tasks (`deally.reporting.tasks.view`), team leaders see their whole team, and owners/admins see everything. Team Dashboard, Account Story and Coaching Review stay manager-only (`deally.reporting.view`).
- **Calls**: any agent (permission `deally.calls.manage`) can start a call from the Calls page. Managers can open the same "New Call" modal and pick an **Assign to** member, which hands ownership over to that sales agent — the call then appears on the agent's home calendar and Calls list.
- **Tasks**: the "New Task" and home "＋ Task" modals include an **Assignee** select. Assigning a task hands ownership to that member, so it shows up in their Tasks list and home day view.

## Sales pipeline, customers & account health

The Pipeline module owns customer accounts, contacts, deals, risk tiers, and Service Reviews.

- **Customer accounts** — deals attach to real customer accounts (`customer_id`) instead of free-form company strings, so one customer can have many contacts and deals. The New Deal modal always picks an existing customer; the server still accepts a legacy company-only path for backward compatibility.
- **Customers page** (`/app/customers`) — account owners, contacts (with a single **primary** contact), an at-risk panel, and a pre-filled Create Deal modal.
- **Deal engagement log** — each deal page merges the deal's calls, the customer's proposals, and a permanent Activity trail (stage moves, lost reasons, notes) into one chronological log. Moving a deal to **Lost requires a reason**; every stage change and note is logged permanently.
- **Risk tiers** (`RiskService`) — every customer is assessed as **Critical**, **High**, **Medium**, or **Low**: Critical = a missed Service Review session or a proposal needing action; High = a *demand account* (open pipeline above the tenant threshold) carrying a negotiation-stage deal; Medium = an open deal; Low = no open deals or 60+ days of inactivity. The demand threshold is a tenant-level `AccountSetting` (default **150,000**), editable in Settings by owners/admins. The pipeline and deal pages surface these tiers, and an open deal on a Critical/High account is flagged **possible lost** (linking to Tasks to resolve the underlying review-call task).
- **Service Reviews** (`ServiceReviewService`) — a recurring per-customer check-in schedule started at the cadence for the account tier (standard 30 / premium 14 / enterprise 7 days). Each schedule keeps a rolling set of **3 upcoming sessions** that top back up when a session is held or cancelled; agents can reschedule (with a time-clash guard), hold, cancel, catch up a missed session, change the cadence (**future sessions only** — already-scheduled upcoming reviews are never rewritten), or end the schedule (removes future sessions only). Creating a deal for a customer without an active schedule prompts to set one up.
- **Proposal editor** — proposals are editable in place from the engagement log and the Proposals page (name, value, package, quote, status); status changes are logged.

### Removing a member (reassignment plan)

Removing a member from the tenant differs by seat:

- **Sales agents** are never forcibly stripped — deactivation routes through a **reassignment plan** screen that lists the agent's customers with suggested owners (least-loaded eligible agents, weighted by deal priority and risk), lets a manager override per customer, bulk-assign, or recalculate, then approve to transfer ownership and remove the membership.
- **Team Leader / Solutions Lead** seats never cascade — they get a fill-the-seat screen with a confirm-removal endpoint that redirects back to the Users page.
- **All other roles** keep the inline Remove form.
- Records the departing agent owned on accounts *outside* the plan are re-homed to that account's owner so nothing is orphaned.

## Running the app

The application is served by Laravel Herd at `https://deally.test`. Frontend assets need `npm run dev` (or `npm run build`) running to be reflected in the browser; run `composer run dev` to start both.

## Calls

The Calls module covers a call from booking to the follow-up it produces.

- **Initiation** — a platform picker, a session type, and a real invitation. The invitation is
  composed, sent through Laravel Mail, and stored verbatim on the call, so what was actually sent
  stays answerable. When no platform is chosen, or delivery fails, the call records that.
- **Unplanned calls** — a `failed` or `no_show` call is recorded with its reason and raises a
  reschedule task, because a missed call with no next step leaves the pipeline with a dead deal.
- **Live call** — dual-stream capture, a real customer panel beside it, and a Findings shelf that
  holds **say**, **ask**, **reference**, **waiting** and **objection** cards distinguished by edge
  treatment rather than colour alone.
- **Post-call summary** — deliberately sparse: real readiness, open deal-status flags, whether a
  proposal was agreed, and a way into the review task.
- **Review task** — the real work. It opens in a modal from the tasks list and carries what DeAlly
  heard, the objections (detected and rep-added, kept apart), what the call left outstanding,
  correctable sentiment and readiness, the correction log, the open flags, and an **Agent
  Performance** score-card (talk ratio, objections handled, AI suggestions, usefulness) computed by
  the same source as the Coaching Review. It **cannot be closed
  while a flag is open** — from the modal or the list.
- **Replay and the full conversation** — every captured window is kept on the private disk *before*
  transcription is attempted, so the review can play the call back and jump to the moment any line was
  said.

### Meeting platforms: the flow is real, the connectors are not shipped

**No meeting platform is connected, and the app says so.** The picker, the data model, the states, the
UI and the tests are real; the provider calls are not implemented, because a Zoom Server-to-Server
OAuth app, a Teams bot registration and a Google service-account joiner all need credentials that
cannot be simulated.

The alternative — a connector returning a well-formed-looking join URL — is worse than having
nothing, because a rep will believe the customer has the link. So `calls.meeting_join_url` has **no
code path that assembles one**, and `bot_join_status` reaches `joined` only from a provider
confirmation. The only shipped implementation of
`Deally\Calls\Contracts\MeetingPlatformConnector` is `UnconnectedMeetingPlatform`, which reports what
it cannot do in the call's own words.

To connect one: add its credentials to the `services.meetings.<key>` block in `config/services.php`
(already present for all three platforms, and commented to say so), implement the contract, register
it with `MeetingPlatformManager::register()`, and let `isConnected()` drive the `connected` flag.

## Live AI assistant (LLM integration)

The Calls module captures the agent's microphone and, when shared, the meeting audio as two independent 4-second streams. Silent windows are dropped in the browser, Laravel sends the rest to the configured speech-to-text provider, persists the resulting `TranscriptLine`, and periodically analyzes the recent conversation — whichever side spoke — for:

- buying signals, intent, objections, competitor mentions, deal risk, and knowledge gaps;
- knowledge-grounded **say**, **ask**, **reference**, and **objection** recommendations;
- a one-line paraphrase of what was just said, which is what the ephemeral stream's **Heard** card shows;
- whether a proposal was actually agreed, which is the only thing that reveals the Create Proposal button.

Analysis deliberately runs on agent lines too. Gating it to customer lines produced the worst possible failure: a rep testing into their own microphone got a working transcript and an always-empty shelf, which reads as a broken provider rather than a missing second stream. The judgement belongs in the prompt, not in a hard gate.

Accepted results are stored as `CallFinding` rows, rendered in the live Findings shelf, restored after reloading, and linked to the source transcript line. Every card in the stream is also written to `call_ephemerals` before the response returns, so "clear the findings" hides cards without deleting rows, and the review task and review page read those rows back. Reps can mark findings **unhelpful**; that feedback creates a deduplicated gap for the Solutions workflow.

Provider credentials and requests stay on the server. `LIVE_AI_DRIVER` accepts `auto`, `groq`, `openai`, or `dummy`; `auto` prefers Groq, then OpenAI, and only uses the deterministic demo driver outside production. Configure the matching `GROQ_*` or `OPENAI_*` variables in `.env.example`. Completed calls reject new audio, chunk retries are idempotent, silence is handled without a fake transcript, and the browser drains both upload queues before ending a call.

## Product guarantees (the non-negotiables)

DeAlly is engineered around twenty product invariants; the app is deliberately built so it cannot get
out of these, and the test suite guards each one. The enforceable-by-code invariants are verified by
feature tests; the policy-level ones (consent, retention ownership) are enforced by how the surfaces
are designed and by what the UI states.

| # | Invariant | Enforced by |
| --- | --- | --- |
| 1 | AI answers come from the knowledge base | `LiveAssistant` grounding + `NO_KNOWLEDGE_PACKAGE`; unanswered → `KnowledgeGap` |
| 2 | The knowledge base is written only via governed paths | `kb.manage`-gated KB writes; `approveIntoKb` is the only creation path |
| 3 | Nothing is deleted | Tasks archive (status `closed`); findings hide, not delete; the wholesale transcript-rewrite endpoint was removed |
| 4 | Consent is by invitation | Real invitation recorded verbatim; policy-level, not a checkbox |
| 5 | The live page shows cards, never a raw transcript | Live UI renders findings cards only |
| 6 | Findings shelf is read-only; interaction lives in the AI panel | Ask/objection/hero/dot-feedback UX |
| 7 | The assistant announces its state | "DeAlly is listening" idle + "AI Asks You" hero |
| 8 | Reassignment changes nothing until approved | Draft plan applied only by `approvePlan` |
| 9 | DeAlly steers, it does not echo | Noticed frames on customer lines; agent lines → steering prompts |
| 10 | Notifications are set at the company level | Per-tenant toggles in Settings (account-manager only) gate emissions |
| 11 | Retention is tier-driven, platform-controlled | RetentionService tier; only platform support changes it |
| 12 | Errors are visible to the person who triggered them | Call-scoped `Activity` history on the call detail modal |
| 13 | Deal-status flags close only inside review surfaces | Flag resolution from review-task modal / full review only |
| 14 | Paraphrases are short, present-tense, never quoted | ≤ 12 words + server-side `normalizeParaphrase` |
| 15 | Ending a call always creates the review task | `CallController::end()` → `firstOrCreate` "Review Call" due +24h |
| 16 | The review task shows the agent performance score | Same snapshot as Coaching Review via `CallReviewBrief::agentPerformance()` |
| 17 | Missed sessions move risk; deal flags move deals | `RiskService` Critical/at-risk separate from deal-risk flag |
| 18 | Cadence changes apply to future sessions only | `changeCadence` never rewrites scheduled sessions |
| 19 | A task is todo or closed — nothing else | Binary status; `scopeOverdue` computed |
| 20 | Every task carries an explicit due time | Date-only values rejected on Tasks + quick-add |

The full list with per-invariant notes lives in **Product guarantees** in
[docs/system-overview.md](docs/system-overview.md).

## Documentation

| Document | What it covers |
| --- | --- |
| [docs/call-lifecycle.md](docs/call-lifecycle.md) | Booking, invitations, the live surfaces, the post-call summary, the review task, corrections, flags, meeting platforms, and how to add a connector |
| [docs/ai-llm-integration.md](docs/ai-llm-integration.md) | Browser capture, transcription, the structured schema, the ephemeral stream, endpoints, configuration, persistence, and provider extension |
| [docs/system-overview.md](docs/system-overview.md) | The product presentation view of DeAlly, with a demo script |

The same setup notes are also served in-app at `/docs` and `/docs/ai`, reachable from the login footer.

## Tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite central database — 292 tests, 1,184 assertions. `DeallySmokeTest` seeds the demo data, provisions the Acme Corp tenant database, and covers guest login, authentication, all module pages, and the call detail/live/summary pages. `LiveAssistantTest` covers dual-stream transcription, speaker metadata, idempotent retries, provider failures, silence, lifecycle timestamps, throttled structured analysis, untrusted-output validation, persisted findings, knowledge-gap feedback, recording retention, replay streaming, the review page's contents, Groq/OpenAI configuration, and credential non-disclosure. `CallLifecycleTest` covers call completion and the demo-transcript guard, including preserving genuinely captured audio. `CallInitiationAndReviewTest` covers the whole initiation and review lifecycle, and a large part of it asserts that the system does **not** invent things: no join URL without a provider response, no `joined` bot without a confirmation, no fabricated customer-panel signals, no paraphrase wrapped in quotation marks, no foreign transcript line on an objection, no "sent" invitation when delivery failed, and a review task that refuses to close while a deal-status flag is open. It also guards the reverse failure — a record nothing reads: the `heard` cards must appear in both the review task and the review page.

Other feature tests cover the deal engagement log (stage moves, required lost reason, notes, possible-lost flag), risk tiers, Service Reviews (setup, cadence, reschedule, hold/cancel, catch-up, end, time clashes, missed → Critical), contacts, call assignment, the proposal editor, the pipeline board/customer picker, the account demand threshold, and the reassignment-plan workflow. Tenant SQLite files are cleaned up after each test.

## Formatting

```bash
vendor/bin/pint
```

---

© 2026 Wyzone Labs. All rights reserved.