# Khazna: implementation plan

This folder says **how** and **in what order** Khazna is built. The requirements themselves live in:

- `khazna.srs.md`: the master SRS (requirement IDs like `FR-DUE-01` are used below)
- `khazna-attendance.srs.md`: the attendance annex

| File | Content |
|---|---|
| `README.md` | Stack decision, roadmap, rules that apply to every phase |
| `00-foundation.md` | Scaffold, architecture, helpers, shared UI kit, CI, deploy |
| `01-mvp-receivables.md` | Clients, contracts, dues, payments, triage |
| `02-money-out.md` | Employees, costs, milestones, payroll, dashboard, forecast |
| `03-attendance.md` | Discord bot, sessions, review, reminders, digests |
| `04-automation-and-payroll-link.md` | Hours → payroll, WhatsApp automation, CRM sync, exports |
| `05-profitability-and-leave.md` | Project time tagging, margin per contract, leave |

---

## 1. Stack decision

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel, current stable major (13 at the time of writing, verify when scaffolding) | New project, so start on the current major. The CRM helpers are plain PHP and port across majors |
| Bridge | Inertia.js (v3 is current, verify the starter kit's version) | No separate API to build and secure. Routing, validation, policies and sessions stay in Laravel |
| UI | Vue 3 (Composition API, `<script setup>`) + TypeScript + Tailwind + shadcn-vue on Reka UI (official Laravel Vue starter kit) | Closest to Blade and Alpine, ergonomic for form-heavy screens, typed |
| Tables | TanStack Table (Vue adapter) in server-driven mode | One consistent table behavior across every list |
| Charts | shadcn-vue chart components. `vue-echarts` only if they fall short | Enough for the dashboard and forecast |
| Forms | Inertia `useForm` + Laravel FormRequests | Validation lives in one place |
| Types | `spatie/laravel-data` + TypeScript transformer, Laravel Wayfinder for routes | PHP and TypeScript cannot drift apart silently |
| Database | MySQL | Same as the CRM |
| Queue / cache | Database driver first, Redis only if needed | One less service to run |
| Audit | `spatie/laravel-activitylog` | Proven, logs old and new values |
| Tests | Pest, Larastan, Pint, ESLint, `tsc --noEmit`, a few Playwright smoke tests | Money code is tested hard, UI lightly |
| Excel / PDF | `maatwebsite/excel`. PDF library decided after a short spike (phase 4) | Client names in Arabic script must still render correctly, see phase 4 |
| SSR | Not used | Internal app behind a login |

Pin the latest stable versions when scaffolding. Do not copy version numbers from this folder.

### Why Inertia instead of Filament, and what it costs

Filament would deliver phase 1 faster: tables, filters, forms and exports come ready-made, and the CRM already uses it. Inertia gives full control of the UI: a triage page designed for the job, a phone-friendly layout, and the freedom to add a client or employee portal later in the same codebase.

The price is that you hand-build what Filament gives for free: the data table, filter bar, money and date inputs, dialogs, toasts, exports. Phase 0 builds that kit once, and every later phase reuses it. The rule that keeps this cheap: **do not invent a new component when the kit has one.**

Checkpoint: at the end of phase 1, compare real time spent against the estimate below. If it is more than 50% over, review this decision before starting phase 2.

### Why Vue instead of React

Both have an official Laravel starter kit with the same features (Inertia, TypeScript, Tailwind, shadcn components, Fortify authentication). The choice comes down to fit:

- **Closest to what the CRM team already knows.** Filament runs on Livewire and Alpine. Vue's HTML templates with `v-if`, `v-for` and `v-model` read like Blade plus Alpine. JSX does not.
- **Khazna is forms, dialogs and tables.** `v-model` bound to Inertia's `useForm` is the shortest path for that kind of screen. React needs controlled inputs and hooks, and its classic bugs (stale closures, `useEffect` dependency mistakes) are a real cost for a small team.
- **Covers every component Khazna needs.** shadcn-vue and Reka UI provide the table, dialog, sheet, select, tabs, calendar and toast. Both also support right-to-left, so an Arabic interface stays possible later.
- **Laravel treats Vue as first-class.** The kit is maintained for the current Laravel release, and Inertia supports it.

What you give up:

- React has the bigger ecosystem and hiring pool, and Recharts. Khazna has one dashboard and one forecast chart, so the shadcn-vue chart components or `vue-echarts` are enough.
- shadcn-vue is a community port of shadcn/ui, so new components arrive later. We only need the basics (table, dialog, sheet, select, tabs, calendar, toast). The components are copied into the repo, so there is no lock-in.

React would win if you planned to hire React-only developers soon, share components with a React Native app, or build heavy interactive visualizations. None of these is on the roadmap.

---

## 2. Roadmap

Effort is a rough guess for one developer. Revise it after phase 1.

| # | Phase | SRS phase | Depends on | Effort | You can then |
|---|---|---|---|---|---|
| 0 | Foundation | none | none | 4 to 6 days | Log in, see the app shell, deploy to staging |
| 1 | MVP receivables, offices and accounts | 1 | 0 | 4 to 5 weeks | Run all receivables without a spreadsheet and see each office's account balances |
| 2 | Money out | 2 | 1 | 4 weeks | See the cash picture of each office and close a month's salaries |
| 3 | Attendance | 3 | 2 | 2 to 3 weeks | Team uses `/check-in` daily |
| 4 | Automation and payroll link | 4 | 2, 3 | 3 weeks | Prepare salaries from approved hours, automate reminders |
| 5 | Profitability and leave | 5 | 3, 4 | 2 weeks | See which contracts make money |

```
0 ─> 1 ─> 2 ─┬─> 3 ─┬─> 4 ─> 5
             └──────┘
```

Phases 1 and 2 can overlap slightly (the employees table can start during phase 1), but never start phase 3 before month-close from phase 2 exists.

---

## 3. Rules that apply to every phase

### Money
- Stored as integer centimes in `bigint` columns and handled through the `Money` value object. Never floats.
- **The server computes every total.** The browser displays amounts, it never adds them. Props carry both the raw integer and the formatted text.
- Every operation that changes money runs in a database transaction and locks the rows it changes.
- **Every movement of money is one ledger entry on one account**, written in the same transaction as the payment, advance, transfer or manual entry that caused it. Entries are never edited or deleted. A mistake is fixed with an opposite entry. An account's balance is computed from its entries and is never typed in.

### Time
- Timestamps are stored in UTC. Calendar dates (`due_on`, `paid_on`, `month`) are `DATE` columns that mean an **Algiers calendar date**, with no time and no timezone shift.
- "Today" always comes from `AlgerianDate::today()`, never from `now()->toDateString()`.
- Display in Algiers time with English formatting (`10,000.50 DA`, `13 Nov 2026`), Latin digits and the Gregorian calendar.

### Security
- Everything Inertia sends in page props is visible in the page source. **Never pass data a user may not see and rely on the UI to hide it.** Salary fields exist only in responses built for users who may see them.
- Every controller action authorizes through a policy. The frontend receives an `abilities` map to show or hide buttons, but only the server decides.
- Receipts and attachments live on a private disk and are served through an authorized route.
- Secrets in `.env` only.
- **Office scope:** a user restricted to an office sees only that office's data. It uses the same `visibleTo($user)` scope as the account-manager rule, and a test checks every list and direct link.

### Code
- Controllers are thin. Business logic lives in single-purpose **Action** classes (`RecordPayment`, `GenerateDues`, `CloseMonth`) that can be called from a controller, a command or a Discord handler.
- Anything that can run twice must be safe to run twice (dues generation, month-close, webhooks, Discord retries).
- Reversals, cancellations and postponements need a reason and are logged.

### Tests
- Money, period, cycle, metrics and attendance-hours code gets unit tests with **hand-computed** expected values.
- Every SRS acceptance scenario in a phase becomes a feature test.
- Every role × resource × action combination is covered by a policy test.

---

## 4. Definition of done

A feature is done when:

1. The requirement ID is met and the matching tests pass.
2. Policy tests cover each role. An unauthorized user gets 403, and no restricted data appears in the page props.
3. The UI is checked on desktop and on a phone width.
4. The change is in the audit log where money or permissions are involved.
5. Lint, Larastan, `tsc` and tests are green in CI.
6. No lazy-loading or N+1 warnings (they are fatal in non-production).

A **phase** is done when all its features are done, the phase's exit checklist is ticked, and it is deployed to staging and tried with realistic data.

---

## 5. Keeping this folder current

- When a decision changes, update the SRS first, then the phase file.
- Tick tasks (`- [ ]` → `- [x]`) as they are finished. Add a "Notes" line under a task when you deviate.
- Open questions are collected at the bottom of each phase file. Close them before the phase starts.
