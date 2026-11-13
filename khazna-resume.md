# خزنة (Khazna) — project summary

Payment-tracking app for SAASCOM: SaaS renewals, custom projects, employee salaries and other recurring costs.

**Status:** proposal agreed in principle, name chosen. Nothing has been scaffolded yet (no `khazna/` folder, no code).

---

## 1. Name

| Item | Value |
|---|---|
| Folder / repo | `khazna` (`/home/yacine/Desktop/work/khazna`) |
| Brand shown in the panel | **خزنة** (`->brandName('خزنة')`) |
| Alternatives that were considered | `saascom-finance`, `saascom-treasury`, `soft-finance-suivi`, `sandouk`, `hsab`, `echeancier` |

---

## 2. Why a separate app (not a module of `soft_client_suivi`)

- The CRM's `Subscription` + `Payment` model tracks **money in only**, tied to a lead, with a single `should_pay_on` date and no renewal cycle. It doesn't fit salaries or projects.
- Salaries must never be visible to CRM agents. A separate app makes that easy to guarantee.
- Same stack, so helpers and conventions port over.

**Stack:** Laravel 12 + Filament 3.3 (same as the CRM), MySQL, Arabic RTL UI, UTC in the database, Algiers (`Africa/Algiers`) for display. Ported helpers: `Money`, `AlgerianDate`, `Period`/`Metrics`, `Phone`.

---

## 3. Core idea: everything is a "due" (échéance)

A due is a payment that must happen on a date. It is money **in** or money **out**, has an amount and a status, and can have several payments recorded against it. The kinds differ only in how dues get created.

| Kind | Direction | How dues are created |
|---|---|---|
| SaaS renewal | in | Contract with a cycle (monthly / quarterly / yearly); the next due is created after the current one is paid or comes close |
| Custom project | in | Milestones fixed at contract creation (e.g. 40 / 30 / 30), due on a date or on a delivery event |
| Salary | out | "Close the month" action creates one due per active employee: base + advances, bonuses, deductions |
| Other recurring costs (hosting, domains, tools, rent) | out | Same as SaaS contracts, reversed |

---

## 4. Data model

- `clients`, `employees` (and optionally `suppliers`)
- `contracts`: type, party, amount, cycle, start/end dates, status (active / paused / canceled), auto-renew
- `dues`: direction, contract, period, `due_on`, `amount_due`, `amount_paid`, status. Unique key on (contract, period) so generation is idempotent
- `payments`: due, amount, date, method (cash, CCP, BaridiMob, transfer), reference, receipt, recorded by
- `salary_adjustments`: advance, bonus or deduction, linked to an employee and a month
- Audit log from day one

A daily command `dues:generate` creates the dues for the next 30 days (same scheduler pattern as the CRM's `notes:remind`).

---

## 5. Triage page (the main screen)

Modeled on the CRM's `TodayReminders` page. Tabs:

**Overdue → Today → This week → This month → Needs attention**

"Needs attention" = a delivered milestone that hasn't been invoiced, or a contract with no next due.

Filters: direction (in / out), kind (SaaS / project / salary / expense), client, assignee.

Row actions:
- **Pay**: one click, remaining amount prefilled, editable for partial payments
- **Postpone**: keeps history with a reason
- **WhatsApp**: opens a prefilled Arabic reminder
- **Advance** (for salaries)

---

## 6. Dashboard and forecast

- Collected this month, outstanding, overdue, MRR (monthly recurring revenue), upcoming renewals, total salary bill
- **30 / 60 / 90-day cash forecast**: expected money in vs out, to see whether the salaries on the 5th are covered
- Built in the same `Metrics` style as the CRM, with hand-computed tests

---

## 7. Security and roles

| Role | Access |
|---|---|
| `owner` | Everything, including salaries |
| `finance` | Everything except salaries (optional split) |
| `account_manager` | Only their own clients' receivables |

Lessons from the CRM's `doc/refactor` backlog, applied from day one:
- **S11**: receipts on a private disk, served through an authorized route
- **M03**: policies on every action (no unauthorized Livewire methods)
- **M07 / S06**: Algiers time and day boundaries handled correctly
- **G07**: soft deletes

---

## 8. Link with the CRM

1. Start standalone and import existing clients from a CSV.
2. Later, add a one-way sync: when a lead becomes *won* in the CRM, a client is created in Khazna. It would use the same `X-Webhook-Secret` pattern as the n8n webhook, so nothing is entered twice.

---

## 9. Phases

1. **MVP**: clients, contracts, dues generation, payments with receipts, triage page
2. **Money out**: payroll month-close, advances, project milestones, dashboard and forecast
3. **Extras**: automatic WhatsApp reminders through n8n (J-7, J-1, J+3), CRM sync, Excel/PDF exports, payslips

---

## 10. Open decisions

| # | Question | Default I would assume if not answered |
|---|---|---|
| 1 | Who uses it: only you, or also an accountant and account managers? | `owner` + `finance` + `account_manager` roles, all optional to use |
| 2 | Currency: DA only, or EUR/USD clients too? | DA only (column kept so it can be added later) |
| 3 | Legal invoices (TVA, timbre, NIF/NIS/RC) or payment tracking only? | Payment tracking only, no invoices in the MVP |
| 4 | Salaries: net amount + adjustments, or CNAS/IRG deductions too? | Net amount + adjustments |
| 5 | Existing CRM `subscriptions` / `payments` data: migrate into Khazna or leave in the CRM? | Leave in the CRM as first-sale payments |

---

## 11. Next step

Create the `khazna` folder with Laravel 12 + Filament 3.3 (same conventions as the CRM), configure Arabic RTL, the Algiers timezone and the panel brand, then build phase 1.

*Paused by the user before the scaffold command ran. Nothing was installed.*
