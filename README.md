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
| `TenantManagement` | `Deally\TenantManagement` | — | — | Tenant provisioning contracts and policies |

### Multi-tenancy

- The central database holds users, tenants, memberships, and subscriptions.
- Each active tenant gets its own SQLite database under `database/tenants/` with a random `instance` key.
- Module routes run under the `deally` middleware group registered in `bootstrap/app.php`, which applies the session, CSRF, `auth`, and `tenant.context` middleware plus route-model binding substitution. `tenant.context` (`SetTenantContext`) runs before `SubstituteBindings` so route-bound models hydrate from the right tenant connection.

### Registration

Modules are registered in `bootstrap/providers.php` via their service providers and PSR-4 autoloaded in `composer.json`:

- `Deally\Calls\` → `modules/Calls/src/`
- `Deally\Core\` → `modules/Core/src/`
- `Deally\Pipeline\` → `modules/Pipeline/src/`
- `Deally\Proposals\` → `modules/Proposals/src/`
- `Deally\Settings\` → `modules/Settings/src/`
- `Deally\Tasks\` → `modules/Tasks/src/`
- `Deally\TenantManagement\` → `modules/TenantManagement/src/`
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

Seed the central database, then create, migrate, and seed the tenant databases:

```bash
php artisan db:seed --class=Database\\Seeders\\DemoSeeder
php artisan deally:tenants:setup
```

Use `--tenant=<uuid|instance>` to provision a single tenant and `--fresh` to rebuild a tenant database:

```bash
php artisan deally:tenants:setup --tenant=acme-corp --fresh
```

### Demo accounts

All demo users log in with the password `password`:

| Email | Tenants | Role |
| --- | --- | --- |
| `alice@example.com` | Acme Corp (owner), Globex (viewer) | owner |
| `bob@example.com` | Globex | owner |
| `charlie@example.com` | Acme Corp | viewer |

## Running the app

The application is served by Laravel Herd at `https://deally.test`. Frontend assets need `npm run dev` (or `npm run build`) running to be reflected in the browser; run `composer run dev` to start both.

## Tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite central database. `DeallySmokeTest` seeds the demo data, provisions the Acme Corp tenant database, and covers guest login, authentication, all module pages, and the call detail/live/summary pages. Tenant SQLite files are cleaned up after each test.

## Formatting

```bash
vendor/bin/pint
```