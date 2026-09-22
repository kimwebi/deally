# DeAlly

DeAlly is a multi-tenant SaaS application for sales teams to track calls, pipeline, tasks, and proposals. It is built on Laravel 13 and the `multitenant/saas-foundation` package, with feature modules under `modules/`.

## Architecture

The application is split into feature modules, each with its own `routes/`, `resources/views/`, and `src/` directory. Modules are autoloaded via PSR-4 in `composer.json` and registered through each module's service provider.

| Module | Namespace | Routes prefix | Views namespace | Responsibilities |
| --- | --- | --- | --- | --- |
| `Core` | `Deally\Core` | — | `core::` | Shared layouts, guest login, tenant binding middleware |
| `Calls` | `Deally\Calls` | `/app/calls` | `calls::` | Call tracking, live call script, summaries |
| `Pipeline` | `Deally\Pipeline` | `/app/pipeline` | `pipeline::` | Sales pipeline |
| `Tasks` | `Deally\Tasks` | `/app/tasks` | `tasks::` | Task management |
| `Proposals` | `Deally\Proposals` | `/app/proposals` | `proposals::` | Proposals and knowledge base |
| `Settings` | `Deally\Settings` | `/app/account` | `settings::` | User account settings |
| `Workspace` | `Deally\Workspace` | `/app/home` | `workspace::` | Workspace dashboard |

### Multi-tenancy

- The central database holds users, tenants, memberships, subscriptions, and teams (`teams` and `team_user` live alongside the central `users` and `tenants`, with cascade foreign keys so deleting a user or tenant automatically removes their team memberships).
- Each active tenant gets its own SQLite database under `database/tenants/` with a random `instance` key.
- Tenant management (central CRUD, provisioning commands, tenant-scoped users/invitations/roles/domains/settings, instance switching, the sole system-owner `superadmin` flag) lives in the `multitenant/saas-foundation` package under `packages/`.
- Module routes run under the `deally` middleware group registered in `bootstrap/app.php`, which applies the session, CSRF, `auth`, and `tenant.context` middleware plus route-model binding substitution. `tenant.context` (`SetTenantContext`) runs before `SubstituteBindings` so route-bound models hydrate from the right tenant connection.

### Registration

Modules are registered in `bootstrap/providers.php` via their service providers and PSR-4 autoloaded in `composer.json`:

- `Deally\Calls\` → `modules/Calls/src/`
- `Deally\Core\` → `modules/Core/src/`
- `Deally\Pipeline\` → `modules/Pipeline/src/`
- `Deally\Proposals\` → `modules/Proposals/src/`
- `Deally\Settings\` → `modules/Settings/src/`
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

### Demo accounts

All demo users log in with the password `password`:

| Email | Acme Corp | Globex |
| --- | --- | --- |
| `alice@example.com` | owner | owner |
| `bob@example.com` | admin | admin |
| `charlie@example.com` | sales-agent · viewer | viewer |
| `david@example.com` | admin | solutions-lead |
| `erica@example.com` | team-leader | — |

> Alice is **the** tenant owner — she owns every demo instance (Acme Corp and Globex). The other members carry per-instance roles, which is what exercises multitenancy: Bob is an administrator in both instances; Charlie is a sales agent in Acme and a read-only viewer in Globex; David is an administrator in Acme and Globex's solution lead; Erica leads the Acme East Pod team. Erica's and David's accounts are seeded by `DeallyAccessSeeder` (`Erica Valdez` teammates land in the East Pod, watching the team workspace, team pages and team reporting). A tenant owner can change any member's role from the Administration → Users page.

## Roles, calls and task assignment

- The Administration → Roles page lists only the roles in use in the active instance (a **global** role appears when a member of this instance holds it, e.g. Tenant Owner, Viewer, Sales Agent, Team Leader), plus any roles created for this instance. The platform-level Super Administrator and unused global roles are never listed. Member counts are scoped to the active instance.
- Owners and admins can open the Roles, Teams and Users administration pages.
- **Calls**: any agent (permission `deally.calls.manage`) can start a call from the Calls page. Managers can open the same "New Call" modal and pick an **Assign to** member, which hands ownership over to that sales agent — the call then appears on the agent's home calendar and Calls list.
- **Tasks**: the "New Task" and home "＋ Task" modals include an **Assignee** select. Assigning a task hands ownership to that member, so it shows up in their Tasks list and home day view.

## Running the app

The application is served by Laravel Herd at `https://deally.test`. Frontend assets need `npm run dev` (or `npm run build`) running to be reflected in the browser; run `composer run dev` to start both.

## Live AI assistant (LLM integration)

Live calls are transcribed and turned into real-time solution suggestions for the agent using the OpenAI API (`LiveAssistant`), with a deterministic `DummyAssistant` fallback when no API key is set. See [docs/ai-llm-integration.md](docs/ai-llm-integration.md) for the full flow, endpoints, config, and how to swap in another provider.

## Tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite central database. `DeallySmokeTest` seeds the demo data, provisions the Acme Corp tenant database, and covers guest login, authentication, all module pages, and the call detail/live/summary pages. Tenant SQLite files are cleaned up after each test.

## Formatting

```bash
vendor/bin/pint
```