# Phase 0: Foundation

| | |
|---|---|
| SRS phase | None. Prerequisite for phase 1 |
| Effort | 4 to 6 days |
| Depends on | The decisions in 0.1 |
| Outcome | An empty but production-shaped app: login, roles, responsive layout, shared UI kit, tested helpers, green CI, deployed to staging |

---

## 0.1 Decisions to close first

| Decision | Recommendation |
|---|---|
| React or Vue | **Vue 3** (decided, see `README.md` section 1) |
| Laravel major | The current stable major (13 at the time of writing). Check the official Vue starter kit supports it. The CRM helpers are plain PHP and port across majors |
| Money storage | Integer centimes. If the CRM `Money` helper stores decimals, port its API but keep integer storage |
| Hosting | One VPS with Nginx, PHP-FPM, MySQL, Supervisor for the queue, cron for the scheduler. Managed alternatives (Forge, Ploi) are fine. Needs a public HTTPS domain, because Discord must reach the app (phase 3) |
| Repo | `git init`, private remote, short-lived branches merged through pull requests |
| Language and font | English only, left-to-right (decided). One self-hosted font. No runtime call to Google Fonts |

---

## 0.2 Scaffold

The folder already holds the docs, so do not scaffold into it directly.

- [x] Scaffold in a temporary sibling folder with the Laravel installer and the **Vue starter kit** (check `laravel new --help` for the flags).
- [x] Move everything, including dotfiles, into `khazna/` next to `doc/` and the SRS files. Never commit `.env`.
- [x] `git init` and a first commit.
- [x] Check what the starter kit ships. Recent kits include login, password reset, profile and optional two-factor. Keep those, and **remove public registration**. Accounts are created by the owner.
  Notes: Laravel 13.17 / Inertia 3 / Fortify 1.37 / Wayfinder / passkeys. Registration feature disabled, register page and registration test removed.
- [x] Set `APP_TIMEZONE=UTC`, add `config/khazna.php` with `display_timezone = Africa/Algiers`, `week_starts_on = saturday`, and the default locale `en`.
- [x] In non-production environments turn on `Model::shouldBeStrict()` (lazy loading, missing attributes and silently discarded attributes all throw).
- [ ] Use Inertia partial reloads and deferred props where a page has heavy parts (dashboard cards in phase 2).

---

## 0.3 UI and formatting check (half a day)

Do this before building screens. The interface is English and left-to-right.

- [x] `<html lang="en">` and one self-hosted font (no runtime call to Google Fonts). Choose a font that also covers Arabic script, or add a fallback font, because client names and notes may be typed in Arabic.
  Notes: Instrument Sans is self-hosted by the starter kit build (Bunny plugin, no runtime call); Arabic falls back through Noto Sans Arabic / Tahoma. The starter Welcome page's rsms.me runtime font link was removed.
- [x] Format money, numbers and dates with `Intl` in English: `10,000.50 DA`, `13 Nov 2026`, Latin digits, Gregorian calendar, 24-hour times shown in Algiers time.
- [x] Put `dir="auto"` on user-entered text (client names, notes, report text) and wrap names in `<bdi>` inside sentences and tables. An Arabic name then renders correctly inside the English page.
- [x] Use Tailwind logical classes (`ms-`, `me-`, `ps-`, `pe-`, `text-start`) instead of `ml-`, `mr-`, `left-`, `right-`. It costs nothing and keeps an Arabic interface possible later.
- [ ] Check these primitives on desktop and at 375 px width: sidebar, dropdown, select, dialog, sheet, tabs, date picker, toast, pagination, table.
- [x] Check that the chart components show a 90-day forecast with weekly buckets and readable labels. If they do not, try `vue-echarts` now.
  Notes: the shadcn-vue chart wrapper generated in this version was incomplete, so `vue-echarts` is used for the 90-day weekly forecast (`ForecastChart.vue`).

**Exit criteria:** one demo page showing every primitive above, reviewed by you, including a client with an Arabic name.

---

## 0.4 Architecture

```
app/
  Actions/            one class per business operation (RecordPayment, GenerateDues, CloseMonth…)
  Console/Commands/   dues:generate, attendance:auto-close…
  Data/               spatie/laravel-data classes: the typed shape of Inertia props
  Discord/            interaction router, handlers, DiscordClient (phase 3)
  Enums/              Role, DueStatus, Direction, ContractType, Cycle, PaymentMethod…
  Http/
    Controllers/      thin: authorize, call an Action, return Inertia::render
    Requests/         FormRequests (validation + messages)
  Models/
  Policies/
  Queries/            query objects for list pages (DueTriageQuery, ClientIndexQuery…)
  Support/            Money, AlgerianDate, Period, Phone, Metrics
resources/js/
  pages/              one folder per domain (triage, clients, contracts, payroll…), one .vue file per page
  components/         shared UI kit (0.7)
  composables/        useAbilities, useMoney…
  types/              generated types + hand-written helpers
lang/en/              backend messages (validation, Discord replies, notifications)
```

Patterns to follow:

- **Action:** a plain class with one public method. It opens its own transaction, locks what it changes, and throws a domain exception on a rule violation. Controllers, commands and Discord handlers all call the same Action.
- **Query object:** builds the filtered, sorted, paginated query for a list page from the URL query string. The same object feeds the Excel export in phase 4, so the export always matches the screen.
- **Page props:** built from `Data` classes, never from raw models. This is what keeps salary fields from leaking and gives TypeScript its types.
- **Abilities:** each list row and page includes `abilities: { pay: true, postpone: true, … }` computed from policies. The UI uses it only to show or hide.
- **Errors:** domain exceptions become validation errors or flash messages in plain English. Never show a raw stack message.
- **Text:** backend messages (validation, Discord replies, notifications, reminder templates) live in `lang/en/` and are read with `__()`, so the wording changes in one place. Vue pages use plain English text, with no frontend translation library. If a second language becomes likely, add one to the frontend before the number of screens grows.

---

## 0.5 Time and money helpers

Port the CRM helpers `Money`, `AlgerianDate`, `Period`, `Phone` and the `Metrics` pattern, and rewrite whatever does not fit integer centimes. Write the tests **first**, with hand-computed values.

| Helper | Must do | Test cases |
|---|---|---|
| `Money` | Value object over integer centimes: add, subtract, compare, multiply by a ratio, `allocate` into parts with remainder handling, format, parse user input | `allocate(100,000.00 DA, 40/30/30)` = 40,000 / 30,000 / 30,000. `allocate(100,000.01 DA, 40/30/30)` keeps the centime total exact. Parse `"10,000.50"`, `"10000.50"` and `"10 000,50"` (users may type French style) |
| `AlgerianDate` | `today()`, `toAlgiers(utc)`, `startOfDayUtc(date)`, week and month boundaries with Saturday start | 23:30 UTC on 14 Jan is 00:30 on 15 Jan in Algiers. A due dated the 15th is "Today" at that moment, not "Overdue" |
| `Period` | Period keys: `2026-12` (monthly), `2026-Q4` (quarterly), `2027` (yearly), `once` | Next period, start and end dates, parse and format |
| Cycle arithmetic | Add N months to an anchor date | Monthly from Jan 31 gives Feb 28 (Feb 29 in 2028), then Mar 31. Yearly from Feb 29 gives Feb 28 in a common year |
| `Phone` | Normalize Algerian numbers to a canonical form and to the digits-only form `wa.me` needs | `0550 12 34 56`, `+213 550 12 34 56`, `00213550123456` and `+213 (0)550…` all give `213550123456`. Landlines and invalid input are rejected |
| `Metrics` | Skeleton only. Filled in phase 2 | none yet |

---

## 0.6 Auth and roles

- [x] `Role` enum: `owner`, `finance`, `account_manager`. One role per user.
- [x] `users` has `role` and `active`. A deactivated user cannot sign in, and a signed-in session of a deactivated user is ended on the next request.
- [x] Base policy class and a `Gate` for `view-salaries` (owner only, configurable later).
- [x] `users.office_id` (nullable, empty means all offices). One `visibleTo($user)` pattern covers both the account-manager scope and the office scope, so no controller invents its own rule.
- [x] Shared Inertia props: `auth.user`, `auth.role`, flash messages, and a small public settings subset.
- [x] `useAbilities()` composable that reads the `abilities` prop.
- [x] Test helper `actingAsRole(Role::…)` for feature tests.
- [x] A **role matrix test** file that grows with every phase: for each route, which roles get 200 and which get 403.

---

Notes:
- `RoleAccessTest` covers inactive sign-in, session termination, the salary gate, and role casts.
- `VisibilityScopeTest` uses a fixture model to prove the office and owner scopes, including their intersection.
- `RoleMatrixTest` starts with the dashboard and guest redirect; add a row for every route added in later phases.
- `spatie/laravel-activitylog` is installed and its migration exists locally, but 0.8 owns and commits that migration.

## 0.7 Shared UI kit

Build these once, with a phone layout and Storybook-free demo pages:

| Component | Contract |
|---|---|
| `AppLayout` | Sidebar that collapses on phones, page title slot, role-aware menu |
| `PageHeader` | Title, description, action buttons |
| `DataTable` | TanStack Table (Vue adapter) in manual mode. Reads and writes `page`, `sort`, `filter[...]` in the URL. Uses partial reloads. Switches to stacked cards on phones. Empty and loading states |
| `FilterBar` | Select, search, date range, tabs with counts. Reads and writes the same URL parameters |
| `MoneyInput` | Accepts spaces, commas and dots when typing, returns integer centimes, shows formatted text on blur |
| `MoneyText` | Shows a server-formatted amount, with a red or green variant for direction |
| `DateInput` | Date picker returning `YYYY-MM-DD` as an Algiers calendar date |
| `StatCard` | Label, value, optional delta, deferred-loading skeleton |
| `FormDialog` | Dialog wrapping an Inertia form with validation errors, a pending state and double-submit protection |
| `ConfirmDialog` | Confirmation with an optional required reason field |
| `StatusBadge` | One place that maps statuses to colors and labels |
| `FileInput` | Upload with type and size checks, preview for images |
| `Toast` | Reads the flash messages |
| `OfficeSwitcher` | "All offices" or one office, kept in the URL. Hidden for users restricted to one office |
| `AccountSelect` | Select grouped by office, showing the balance. Only lists accounts the user may see |
| `EmptyState` | Message plus a call to action |

Exit check: a demo page uses every component, in light and dark if dark mode is kept, on desktop and a 375 px phone.

Notes:
- `AppLayout` is the starter kit layout; the sidebar now filters items by `roles`, and the UI kit entry is owner-only and dev-build-only.
- `DataTable` uses TanStack Table v9 `useTable` in manual mode and writes `page`, `per_page`, `sort` and `direction` to the URL with a partial reload of the `page` prop.
- `FilterBar`, `OfficeSwitcher` and `AccountSelect` share the same URL helpers, so filters survive reloads and are shareable.
- `/dev/ui` is local/staging only and demonstrates all 0.7 components, including loading, empty, dialog, file and toast states.
- Money input parsing treats a trailing separator with one or two digits as decimals; a three-digit group is thousands. Automated JS tests arrive with the 0.9 tooling.
- Review the demo at 375 px and in dark mode before the phase exit; that manual review is still on the exit checklist.

---

## 0.8 Audit trail base

- [x] Install `spatie/laravel-activitylog`. Log only dirty attributes, and log old values.
- [x] A trait `Auditable` that every money, contract and permission model uses.
- [x] Mask sensitive fields: password, remember token. Salary fields are logged, but the audit log screen is owner-only.
- [x] Mark the log table as append-only in code (no update or delete from the app).
- [x] Reason capture: a way to attach a reason to the change being logged (a request-scoped value read by the logging hook).

---

Notes:
- `App\Models\Concerns\Auditable` uses `logFillable`, `logOnlyDirty`, old values, and excludes password and token fields.
- `App\Models\Activity` throws on update and delete, so the table is append-only from application code.
- `App\Support\AuditContext` is request-scoped; `Auditable::beforeActivityLogged()` copies its reason into the activity properties.
- The `view-audit-log` gate is owner-only. The screen itself arrives with the feature that needs it.
- Salary attributes are not excluded, so they are logged; access to the log screen is what stays restricted.

## 0.9 Quality gates and CI

- [x] Pest with an in-memory or dedicated test database that matches production (MySQL, not SQLite, because of generated columns and locking in later phases).
- [x] Larastan at a strict level, Pint for formatting, ESLint and Prettier, `tsc --noEmit`.
- [x] CI pipeline (GitHub Actions or equivalent): install, build assets, lint, static analysis, tests. Fails the pull request.
- [ ] Optional pre-commit hook running Pint and ESLint on staged files.
- [ ] A Playwright smoke test (login → open triage) that runs against staging after deploy.

---

Notes:
- Pest uses MySQL `khazna_test`; locally via `.env.testing`, in CI via `phpunit.xml` and a MySQL 8.4 service.
- Larastan runs at level 8. `vp check` covers formatting and linting, and `vue-tsc --noEmit` covers TypeScript.
- `composer ci:check` is the single CI command: format/lint, TypeScript, Pint, PHPStan, then Pest.
- The optional pre-commit hook is not installed; `composer lint:check` is the manual substitute.
- Playwright has a login-to-dashboard smoke test. The triage step is `fixme` because `/triage` belongs to phase 1, so this box stays open until that route exists.
## 0.10 Environments, deploy and backups

- [ ] Three environments: local, staging, production, each with its own `.env`, database and Discord application (phase 3).
- [x] Deploy script: pull, `composer install --no-dev`, build assets, migrate with `--force`, clear and warm caches, restart the queue worker. Run on staging first.
- [x] Supervisor program for `queue:work`. Cron entry for `schedule:run` every minute.
- [ ] HTTPS with automatic renewal. For local Discord testing in phase 3, use a tunnel such as `cloudflared`.
- [ ] **Backups:** daily database dump plus the private storage disk, copied to a different provider. Retention 30 days. **Do one restore test now**, not at go-live.
- [ ] Error tracking (Sentry or equivalent) and an uptime check on a health endpoint.
- [x] Log rotation, and make sure logs never contain amounts tied to salaries or full phone numbers.

---

Notes:
- `deploy/deploy.sh` is portable and parameterised by `APP_DIR`, `PHP_BIN`, `COMPOSER_BIN` and `NPM_BIN`; it uses maintenance mode, `composer install --no-dev`, asset build, migrations, cache warm-up and a queue restart.
- `deploy/supervisor/khazna-worker.conf` and `deploy/cron/khazna` contain placeholder paths; change them to the chosen host before installing.
- `deploy/backup.sh` dumps MySQL and `storage/app/private`, optionally copies through rclone, and applies a 30-day retention. Supply credentials from a secret manager, never from the cron line. `deploy/cron/khazna` is a ready `/etc/cron.d` file (user column included) that runs the scheduler every minute and the backup daily at 02:30, reading `DB_*` from the app `.env`. A failed deploy leaves the app in maintenance mode on purpose.
- Error tracking, uptime monitoring, HTTPS renewal and the three real environments still need the host decision. The `/up` health endpoint already exists.
- The backup restore test is user-owned and remains on the exit checklist.
## 0.11 Seed data

- [x] Factories for every model added in later phases (add them as you go).
- [ ] A `DemoSeeder` with two offices in different cities, their accounts, realistic Algerian names, phone numbers, amounts in DA, paid and unpaid dues across months. Used for UI work and for the staging demo. Never run it in production.

---

Notes:
- `UserFactory` is the only factory needed in phase 0; add one factory with each model in later phases.
- `DemoSeeder` creates the owner, finance, and two account-manager accounts, with office ids 1 (Algiers) and 2 (Oran).
- Offices are now seeded in phase 1.1; accounts and dues remain deferred until their sections exist.
- `DatabaseSeeder` calls `DemoSeeder` outside production, and `DemoSeeder` throws in production. The demo password defaults to `password`; override it with `DEMO_PASSWORD`.
## Exit checklist

- [ ] Staging is online over HTTPS, login works, registration is closed.
- [ ] The demo page and the shared UI kit are reviewed.
- [x] Helper tests pass with hand-computed values.
- [ ] CI is green and blocks merging when red.
- [ ] A backup was taken and restored successfully.
- [x] The role matrix test exists, even if nearly empty.

## Risks

| Risk | Mitigation |
|---|---|
| Arabic client names render badly in the English page | The 0.3 check: `dir="auto"` on user text and `<bdi>` around names |
| The UI kit grows into a big side project | Build only the components in 0.7. Add another only when a phase needs it |
| Time or money bugs appear in production | Hand-computed tests before any feature code, and the rules in `README.md` section 3 |
| Hosting is chosen too late | Decide in 0.1. Discord needs a public HTTPS URL from phase 3 |
