# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 13 + Inertia 3 + React 19 application being built into the multi-tenant
invoicing/financial-management SaaS described in `PRD-Multi-Tenant-Invoicing-Platform.md`.

Built on the Laravel React starter kit with **teams** as the tenant. The domain now covers
products, distributors, invoices (with challan, print and Excel), payments received, raw
materials with purchases and stock levels, vendors with bills and payments made, expenses, and
a financial report. Deployment is live: push to `main` builds a Docker image and deploys it.

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
- **Permissions** — `TeamPermission` covers the whole domain, split **view** from **manage**
  because that is the distinction the business makes: a salesperson needs to see products to
  raise an invoice without being able to reprice them.

`TeamRole::permissions()` is a **starting point, not a cage**. `team_members.permissions` is a
nullable JSON column: **null means "follow the role"**, and an array — including an empty one —
means "this member specifically". The two differ deliberately, and `Membership::resolvedPermissions()`
is the only place that decides. Unknown strings are dropped, so removing a case from the enum
stops granting anything even if a stale row still names it.

Enforcement is `EnsureTeamPermission` on **every** domain route
(`->middleware(EnsureTeamPermission::class.':invoice:update')`; several values mean any one
will do). The `can` map shared by `HandleInertiaRequests` and read through `useCan` hides menus
and buttons, but hiding is a courtesy — **a hidden button is still a reachable URL**, so the
route is what actually decides. `tests/Feature/Teams/PermissionEnforcementTest.php` asks for
the addresses directly.

The **owner keeps every permission always** — someone has to be able to hand access back after
a mistake. Admins get everything except deleting the company, including managing members, since
assigning access is what the settings screen is for.

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

### Invoicing domain

Tables are tenant-scoped by `team_id` and reached through relations on `Team`
(`$team->products()`, `->distributors()`, `->invoices()`) rather than a global scope — there
is no `where('team_id', ...)` to forget.

Two rules hold the books together; break either and the numbers stop reconciling:

- **Money is whole integers** — no floats, no decimals, no minor units. Columns are
  `bigInteger` holding whole currency units, form requests validate `integer` (not `numeric`)
  so a fractional price is rejected rather than rounded, and props go to React as ints. There
  is no conversion step in either direction; `App\Support\Money::fromInput()` exists only to
  coerce anything that reaches the domain by another route.
- **The server recomputes every amount** in `CreateInvoice` from the locked product rows. The
  browser's totals are for the person filling the form and are never read back, so a stale
  price or a hand-edited request cannot decide what an invoice is worth.

Concurrency, because several staff at one company sell at the same time:

- **Invoice numbers** — `App\Support\InvoiceNumbers` layers a Redis `Cache::lock` (cheap
  contention, per company) over `SELECT ... FOR UPDATE` on `invoice_sequences` (correctness,
  survives Redis being down) over a unique index on `(team_id, invoice_number)` (backstop).
  Allocation happens *inside* the invoice transaction, so a rejected invoice returns its
  number instead of leaving a hole.
- **Stock** — products are locked `FOR UPDATE` in ascending id order, always after the
  distributor, so concurrent invoices queue rather than deadlock. Quantities are summed per
  product before the check: the same product on two lines must fit *together*.
- **Delivery status** — `UpdateDeliveryStatus` re-reads the status under the lock, so two
  people pressing the same button move stock once. `DeliveryStatus::isLive()` is the single
  question behind both stock and money: a void invoice (cancelled/returned) holds neither.

The running account is the other invariant. A distributor's ledger is invoices (charges) and
payments (credits) interleaved — `App\Support\DistributorLedger`, ordered by document date,
then by `created_at` (when it was entered), with invoice-before-payment and id only breaking a
same-second tie. Entry time is the tie-breaker because forcing invoices first reordered the
common case of an advance paid and then invoiced against the same day. That single walk does two jobs: the
statement screen renders it, and `RecalculateDistributorBalance` writes it back onto each
invoice's `previous_dues`/`total_amount` and onto `distributors.balance`. They cannot
disagree, because they are the same code.

Changing how the ledger orders or values anything makes every stored figure stale until that
distributor is next touched. `php artisan app:recalculate-balances` replays every account; it
recomputes from invoices and payments and never modifies them, so it is safe to re-run.

Each invoice storing the balance before and after it makes it a statement you can hand over —
and means **any change invalidates every later line for that distributor**. So editing an
invoice, voiding one, or recording a payment never patches a row: it replays the account.
`UpdateInvoice` returns the old stock before checking the new quantities, so an edit is
measured against stock that already includes what it was holding, and it replays both ledgers
when an invoice moves between distributors. Invoice numbers never change on edit — they are on
documents already printed.

Payments are against the account, not against one invoice, which is how the trade settles. The
note printed beside an invoice's totals is derived: the payments received after that invoice
and before the next one to the same distributor.

**The account is a plain running total and nothing on the invoice form alters it.** Balance,
plus what each invoice charges, less what each payment settles — full stop.

Two invoice fields change only what the paper says:

- `previous_dues_override` — a figure typed into Previous Dues. It is printed in place of the
  account's figure and folded into `total_amount`; `previous_dues` still records what the
  account actually said. Requires an explicit `previous_dues_override: true` in the request,
  so a client holding a stale figure cannot set one by accident — that bug repeatedly rewrote
  balances and wiped out advances.
- `hide_previous_dues` — print the invoice with no dues line at all, totalling the goods alone.

Neither reaches `DistributorLedger`, so the statement and the balance are identical whatever
was printed, and there is no `adjustment` line. Submitting without the opt-in clears the
override, which is how one gets removed.

Products are editable (`UpdateProduct`). Repricing only affects future invoices: invoice items
copy name, SKU and unit price at the moment of sale, and stock entered on the form is an
absolute recount, not a delta.

**Stock writes are explicit `update()` calls, never `increment()`/`decrement()`.** Those two
sync the model's original attributes, so a `saved` listener cannot tell that stock moved — and
that listener is what keeps other people's open invoice forms current. The rows are locked
`FOR UPDATE` first, so read-then-write cannot lose an update.

Live stock uses `App\Support\StockVersion`: a per-company Redis counter bumped (after commit)
whenever a product's stock changes. An open invoice form asks `sales/stock-version` for that
number when a line is added, on tab focus, and on a 20s timer — one Redis read, no database —
and only refetches products with a partial Inertia reload when it has moved. The database
stays the only authority: `CreateInvoice` re-checks stock under a row lock whatever the
browser was last told.

The challan has no stored form: `InvoicePresenter::challan()` renders it from the invoice on
request, so an edited invoice yields an updated challan with nothing to regenerate. It
deliberately omits every priced field, including the distributor's balance.

Verified out-of-band against real Postgres and Redis, not just the sqlite test suite: 20
concurrent processes against 10 units of stock produced exactly 10 invoices, 10 distinct
numbers, and a stock of 0; 30 concurrent invoices produced INV1–INV30 with no duplicates and
an exactly correct distributor balance.

### Stock over time

`products.stock_quantity` says what is on the shelf now and nothing about how it got
there, so `stock_movements` records every change that is **not** a sale or a return:
production, recounts, goods written off. Sales and returns are dated records already —
copying them in would be a second version an edit could leave disagreeing with the first.

`App\Support\ProductStockReport` (screen at `products/stock-report`, in the Products menu) reports a month per
product and **stores no snapshot**: closing stock is today's figure walked *backwards*
through everything dated after the month, and opening is that walked back through the
month itself. A production entered against the wrong day therefore corrects both months
the next time the report is asked for, exactly as `FinancialReport` does for money.

The row adds up left to right — `Closing = Opening + Productions − Sales − Damaged` —
which holds because **Sales is net of what came back**. Fresh returns went back on the
shelf and are shown in their own column so the netting is visible; damaged returns and
warehouse write-offs share the Damaged column. `Balance` is the check column: stock on the
shelf less everything the books say ever moved, and it is zero unless `stock_quantity` was
written without a movement row.

`RecordStockMovement` writes the movement and the new stock together under one
`lockForUpdate()`, and `CreateProduct`/`UpdateProduct` record the opening figure and any
recount the same way. Any future code that moves stock must do likewise or the report
silently walks past it.

### Buying and spending

`App\Support\VendorLedger` is the mirror of `DistributorLedger` and deliberately the same
shape: bills are debits, payments made are credits, and `vendors.balance` is what is still
payable (negative means the vendor holds an advance). Same ordering rule, same replay through
`ReplayVendorBalance`, and **no override and no adjustment** — a plain running total is what
makes a statement reconcilable, which the sales side learned the hard way.

**Expenses are not vendor bills.** A bill is owed to somebody and settled later; an expense is
money already gone. Keeping them apart is what lets the report show what is still payable
separately from what has been spent. Categories are a fixed enum (`ExpenseCategory`) because
the report groups by them.

`App\Support\FinancialAnalytics` is the trend band on top of that screen: the same figures,
bucketed by month or by year, so a line can be drawn through them. It reads the same tables
under the same rules, and `FinancialAnalyticsTest` asserts a bucket equals what
`FinancialReport` states for that month rather than trusting that it does. It carries its own
period control, separate from the report's `from`/`to` — a chart of one month is one dot.
There is **no profit line here either**, for the reason below; `net` is revenue less what was
spent and billed, an operating result, labelled as one on screen.

Charts are hand-drawn SVG (`modules/finance/components/charts/`) — three lines and a ring do
not justify a charting dependency. Their palette is **fixed and deliberately not the company
theme colour**: series identity is carried by hue, and a tenant free to pick pale yellow cannot
be allowed to set it. The eight slots are a validated categorical order (CVD-checked against
this app's white surface, assigned by position, never cycled); a ninth series folds into
Other instead of inventing a hue. Every chart ships a legend and a table, which is what
answers the palette's sub-3:1 contrast warning rather than dismissing it.

`App\Support\FinancialReport` recomputes on every request from the same tables the screens
read, so correcting an invoice yesterday corrects last month's report. It deliberately shows
**no profit line**: an invoice records what a product sold for, not what it cost, so profit
would be a guess dressed up as a figure. Balances (`standing`) are as of today and are not
filtered by the period — "what is owed to us" has no date range.

### The platform panel (`/majedul`)

Whoever runs this system, as opposed to the companies using it. `users.is_super_admin` is a
flag set only by `app:create-super-admin` — never mass-assignable, never reachable from a
request. `EnsureSuperAdmin` redirects visitors to the platform login and answers **404** to a
signed-in company user, so the panel does not announce itself to somebody who guessed the path.

**Companies cannot create companies.** `TeamController::store` refuses anyone but a platform
admin: the system is sold per company, and a customer minting more would make that meaningless.

`teams.suspended_at` closes a company to everyone in it, owner included, via
`EnsureTeamMembership`. Nothing is deleted — the books wait.

Subscriptions follow the same rule the sales ledger learned: **`teams.paid_through` is derived,
never incremented.** Each `subscription_payments` row records the period it bought
(`covers_from`/`covers_to`), and `ReplaySubscription` recomputes the date as the latest
`covers_to`. Correcting a payment therefore fixes the date, and deleting one gives the period
back. `RecordSubscriptionPayment` starts coverage from the later of the current `paid_through`
and the payment date, so paying late does not hand back the months they went without.

`SubscriptionStatus` derives active/overdue in one place for both the panel and the banner
inside the company. **The date never blocks anything** — only `suspended_at` does, and a person
sets that.

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
  layout. `CompanyLayout` is the tenant shell — coffee top nav, no sidebar; `AppLayout` is the
  starter kit's sidebar shell and is now only the fallback.
- `components/ui/*` is shadcn/ui (see `components.json`); everything else in `components/` is
  app-specific. Tailwind v4 via the Vite plugin — no `tailwind.config.js`.
- The brand palette is **`coffee-50` … `coffee-900` plus `gold-100` … `gold-600`**, defined once
  in `resources/css/app.css`. Every surface reaches for those tokens rather than a raw hex, so a
  rebrand is those values and nothing else. Gold is for emphasis only — none of its steps pass
  contrast as text on white.
- **A company can repaint the app** in its own colour (`teams.theme_color`, entered as RGB on
  the company settings screen). Null means the house palette, so a company that never opens the
  setting looks exactly as before. Only the base colour travels: `:root[data-company-theme]`
  derives all ten `coffee-*` steps from `--brand-base` with `color-mix()`, set on `<html>` by
  `app.blade.php` (no flash on first paint) and kept in step by `useCompanyTheme` (company
  switching, saving a colour). Gold is deliberately not re-tinted — it is the accent that has to
  stand out *against* the brand colour. `App\Support\BrandColor` is the only place that encodes
  the colour and the only place that darkens it: every dark surface carries white text, so a
  chosen colour too pale for 4.5:1 is darkened when applied while what they picked is what is
  stored. Never reimplement that arithmetic in TypeScript. **SVG art must reach for the same
  variables** — `StarBackdrop` (the dashboard banner) and the placeholder `WaveMark` do, via
  `style={{ stopColor: 'var(--color-coffee-800)' }}` for gradient stops (presentation attributes
  do not resolve `var()`) and `fill-*` utilities for flat fills. A hardcoded hex anywhere is a
  patch of the old palette that will not follow a company's colour.
- Dates: `sold_at` is a **calendar date with no time of day**, so render it with `formatSaleDate`.
  `formatSaleDateTime` is only ever given a real timestamp such as `created_at`. `APP_TIMEZONE`
  must be set to the business's timezone — `today()` decides which sales reach the dashboard.
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
