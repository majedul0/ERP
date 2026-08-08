# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 13 + Inertia 3 + React 19 application being built into the multi-tenant
invoicing/financial-management SaaS described in `PRD-Multi-Tenant-Invoicing-Platform.md`.

The current code is the Laravel React starter kit with **teams** wired in — Phase 1 of the
PRD's build plan (auth + tenant scaffolding). None of the invoicing domain
(invoices, distributors, vendors, products, payments, expenses) exists yet.

Reconcile these before writing tenant-scoped code:

- The PRD says tenants are `companies` with a `company_id` FK and an `IdentifyTenant`
  middleware. The implementation calls them **teams**, scopes via `team_id`, and enforces
  membership with `EnsureTeamMembership`. Follow the code, not the PRD's naming.
- The PRD assumes Sanctum and `barryvdh/laravel-dompdf`. Neither is installed — auth is
  **Fortify**, and no PDF library has been chosen yet. Horizon *is* installed and queues run
  on Redis.

## Commands

PHP is provided by Laravel Herd (`C:\Users\rtmsa\.config\herd\bin\php.bat`). It resolves in
**PowerShell but not in the Bash tool** — run `php`/`composer`/`artisan` commands through PowerShell.

```powershell
composer setup        # install deps, .env, key, migrate, npm install, build
composer dev          # serve + queue:listen + vite, all concurrently
composer ci:check     # exactly what CI runs: eslint, prettier, tsc, then @test
composer test         # config:clear, pint --test, phpstan, artisan test
composer lint         # pint --parallel (PHP formatting, writes)
npm run lint          # eslint --fix
npm run types:check   # tsc --noEmit
```

Single test / subset:

```powershell
php artisan test --filter=test_teams_can_be_created
php artisan test tests/Feature/Teams
php artisan test --testsuite=Unit
```

Local services (Postgres 16 + Redis 7) come from `docker-compose.yml`; `.env` points at
`127.0.0.1:5432`, database `my_app`. Queue, cache, and session all run on Redis. Tests ignore
all of this — `phpunit.xml` forces sqlite `:memory:`, array cache/session, sync queue.

There is **no registration route** (Fortify has it disabled), so a fresh database has no way
in. `php artisan db:seed` creates `test@example.com` / `password` with a personal team.
`php artisan storage:link` is required for uploaded logos to resolve.

### Windows / Herd caveats

Horizon requires `ext-pcntl` and `ext-posix`, which **do not exist on Windows PHP**:

- `composer install` / `composer setup` need
  `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` on this machine. CI
  (ubuntu) and the production Docker image both have the extensions, so no flag there.
- `php artisan horizon` **cannot run locally**. Use `php artisan queue:work` or
  `composer dev` (which runs `queue:listen`) instead — both consume the same Redis queue,
  so behavior matches; you just lose the dashboard.
- **File uploads break under `artisan serve` unless `TMP`/`TEMP` are passed through.**
  `ServeCommand` strips every environment variable not on its `$passthroughVariables` list,
  which leaves the PHP child with no temp directory; PHP then fails *every* upload with
  `UPLOAD_ERR_NO_TMP_DIR` regardless of size, surfacing as Laravel's `uploaded` validation
  error. `AppServiceProvider::configureLocalServer()` appends both variables. Nginx/php-fpm
  in production never hits this, so do not "simplify" that method away.

## Architecture

### Team-scoped routing

Tenant context lives in the **URL path**, not the session alone. Team-scoped routes sit under
a `{current_team}` prefix (`routes/web.php`) resolved by slug:

```php
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
```

Three pieces make this work together:

- `EnsureTeamMembership` (`app/Http/Middleware/`) resolves the team from the `current_team` or
  `team` route param, 403s non-members, and **switches the user's current team to match the URL**.
  It takes an optional minimum-role arg: `EnsureTeamMembership::class.':admin'`.
- `SetTeamUrlDefaults` (appended to the `web` group in `bootstrap/app.php`) calls
  `URL::defaults(['current_team' => ..., 'team' => ...])` so route helpers and Wayfinder never
  need the team passed explicitly.
- `HasTeams` (`app/Concerns/`) is the single home for membership queries — `belongsToTeam`,
  `teamRole`, `switchTeam`, `fallbackTeam`, `toUserTeam`, `toTeamPermissions`. Add team logic
  here, not to `User` directly.

Tables: `teams`, `team_members` (pivot, carries `role`), `team_invitations`. `Team` is
soft-deleting, keyed by `slug`, and regenerates its slug on name change via
`GeneratesUniqueTeamSlugs`.

### Authorization

Two layers, both used:

- **Roles** — `TeamRole` enum (owner > admin > member) with `level()`/`isAtLeast()` for the
  middleware's minimum-role check.
- **Permissions** — `TeamPermission` enum; `TeamRole::permissions()` maps role → permissions.
  `TeamPolicy` gates actions (`Gate::authorize('update', $team)`), and
  `$user->toTeamPermissions($team)` ships a `TeamPermissions` DTO to React so the UI can hide
  what the backend would reject.

Complex authorization also lives in Form Requests (`app/Http/Requests/Teams/*`) — e.g.
`DeleteTeamRequest` — rather than in controllers.

### Backend conventions

- Controllers stay thin: validate via a Form Request, delegate to an Action
  (`app/Actions/Teams/CreateTeam.php`), redirect with `to_route()`.
- Multi-step writes go in `DB::transaction()`; `TeamController::update` additionally
  `lockForUpdate()`s the row.
- Props are hand-shaped arrays or readonly DTOs from `app/Data/` (`UserTeam`,
  `TeamPermissions`) — models are not serialized wholesale.
- Flash toasts: `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`,
  consumed by `use-flash-toast.ts` + sonner.
- Reusable validation lives in `app/Rules/` (`TeamName`, `ValidTeamInvitation`,
  `UniqueTeamInvitation`) and `app/Concerns/*ValidationRules.php`.
- Uploads are namespaced per tenant and configured in `config/company.php` — logos on the
  `public` disk at `logos/{team_id}/`, invoice PDFs on the private `local` disk. Actions that
  replace or delete a file read the previous path from the **locked row**, not from the model
  passed in, and delete the old file only after the new path commits.
- Stored files are named by `App\Support\StoredFileName`: the type's `name_prefix` from
  `config/company.php` plus a version — `logos/7/logo-2.png`. The client's filename is never
  used, and the version bumps on every replacement so a new file always gets a new URL and
  cannot be masked by a cached copy of the one it replaced. The database column holds this
  disk-relative path; never an absolute path or a URL.
- PHPStan runs at **level 7** over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`.
  Models carry `@property` docblocks and relations carry generic annotations to satisfy Larastan.

### Queues (Horizon)

Horizon only supports the **Redis** queue driver — switching `QUEUE_CONNECTION` to `database`
or `sync` silently takes it out of the picture. Supervisors live in `config/horizon.php`; the
`environments` array merges into `defaults` rather than replacing it.

- The timeout chain must stay ordered **job `timeout` < supervisor `timeout` (60) <
  `retry_after` (90, in `config/queue.php`)**. Raising a job's timeout past 60s means raising
  both of the others too, or jobs get retried while still running.
- `/horizon` is gated by the `viewHorizon` gate in `HorizonServiceProvider`, which admits only
  the addresses in `HORIZON_ALLOWED_EMAILS` (comma separated, read via
  `config('horizon.allowed_emails')` so `config:cache` is safe). Empty list = nobody, outside
  `local`.
- `horizon:snapshot` is scheduled every five minutes in `routes/console.php`; without it the
  metrics dashboard stays blank.
- The PRD's planned jobs (`GenerateInvoicePdf`, `SendInvoiceEmail`, `UpdateCompanyLedger`,
  `GenerateFinancialReport`) all currently land on the single `default` queue. Split them into
  a second supervisor if the slow render jobs start starving the fast ones.

### Auth (Fortify)

`config/fortify.php` enables **only** `resetPasswords` and `emailVerification` — there is no
registration route; users arrive through team invitations. Fortify's two-factor columns are
migrated (`User`, `UserFactory`, and `SecurityController` all reference them) even though the
`twoFactorAuthentication` feature itself is switched off. `FortifyServiceProvider` maps
Fortify views to Inertia pages and hydrates the login page with invitation context when
`?invitation=<code>` is present.

Post-auth redirects must land inside a team URL, so `LoginResponse`, `TwoFactorLoginResponse`,
and `VerifyEmailResponse` all use `RedirectsToCurrentTeam` to prefix `/{team-slug}`. Any new
Fortify response contract needs the same treatment.

### Frontend

- `resources/js/actions/**` and `resources/js/routes/**` are **generated by Wayfinder** from
  PHP controllers/routes on every Vite build. Never hand-edit them; import from
  `@/routes/teams` or `@/actions/...` instead of hardcoding URLs. Regenerate with
  `php artisan wayfinder:generate --with-form` if they drift — **the `--with-form` flag is
  required**, since the Vite plugin runs with `formVariants: true` and every `.form()` call
  site breaks without it.
- Feature code lives in **modules** under `resources/js/modules/<feature>/`
  (`components/`, `hooks/`, `layouts/`, `types.ts`, `index.ts`). Import relatively inside a
  module, and only through the barrel across modules (`@/modules/company`). See
  `resources/js/modules/README.md`. Starter-kit code (auth, teams, profile/security) is
  still flat and migrates as it is touched.
- Layouts are assigned centrally in `resources/js/app.tsx` by page-name prefix
  (`auth/*` → AuthLayout, `settings/*` and `teams/*` → CompanyLayout + SettingsLayout,
  `dashboard` and `company/*` → CompanyLayout, else AppLayout). Pages do not import their own
  layout. `CompanyLayout` is the tenant shell — ocean top nav, no sidebar; `AppLayout` is the
  starter kit's sidebar shell and is now only the fallback.
- `components/ui/*` is shadcn/ui (see `components.json`); everything else in `components/` is
  app-specific. Tailwind v4 via the Vite plugin — no `tailwind.config.js`.
- React Compiler is enabled (`babel-plugin-react-compiler`), so avoid manual memoization.
- Prettier: 4-space indent, single quotes, 80 cols, Tailwind class sorting. Run
  `npm run format` before `format:check` gates you.

### Tests

Class-based **PHPUnit** (not Pest): `Tests\TestCase` + `RefreshDatabase`, `test_snake_case`
method names, `Inertia\Testing\AssertableInertia` for prop assertions. Feature tests mirror the
controller namespaces under `tests/Feature/`.

## Laravel Boost skills

`boost.json` registers project skills in `.claude/skills/` — `laravel-best-practices` (rules
split by topic under `rules/`), `fortify-development`, `inertia-react-development`,
`wayfinder-development`, `tailwindcss-development`, `infer-conventions`, and
`deploying-laravel-cloud`. Consult the relevant one before non-trivial work in that area.

## Deployment

`deployment-runbook.md` is the target production setup: Docker image → GHCR → Hostinger VPS
behind CyberPanel/OpenLiteSpeed, with Postgres, Redis, and Horizon workers. The `Dockerfile`,
`nginx.conf`, and `deploy.yml` it describes are **not in the repo yet** — only
`.github/workflows/tests.yml` (runs `composer setup` then `composer ci:check`) exists.
