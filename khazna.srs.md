# Software Requirements Specification: Khazna

| Item | Value |
|---|---|
| Version | 0.1 (draft) |
| Date | 2026-11-13 |
| Status | For review. Nothing has been built |
| Company | SAASCOM |
| Source documents | `khazna-resume.md` (project summary), `khazna-attendance.srs.md` (attendance annex) |

---

## 1. Introduction

### 1.1 Purpose

This document specifies the requirements for **Khazna**, an internal application that tracks every payment SAASCOM expects to receive or must make, and the working time of the people behind those payments. It is the single reference for what the system must do, for whom, and in which order it will be built.

### 1.2 Problem statement

- Money **in** (SaaS renewals, custom projects) is currently tracked in the CRM only as first-sale payments, tied to a lead, with one `should_pay_on` date and no renewal cycle.
- Money **out** (salaries, hosting, domains, tools, rent) has no tracking at all.
- There is no way to answer "can we cover salaries on the 5th?" or "which clients are late?" without a spreadsheet.
- There is no record of employee working hours to support salary decisions.
- The company has **two offices in different cities**, each with its own cash and accounts (cash box, CCP, bank). Nothing shows how much money is in each place, or whether one office can cover its own salaries.

### 1.3 Scope

**In scope**

- Clients, employees and (optionally) suppliers.
- Contracts: SaaS renewals, custom projects with milestones, recurring costs.
- Dues (échéances): generation, tracking, partial payments, postponement.
- Payments with receipts, each recorded into a money account.
- Offices (two cities) and money accounts: cash boxes, CCP and bank accounts, a ledger with balances, transfers between accounts, manual entries, categories and reconciliation.
- A triage page that shows what needs action today.
- Payroll: month-close, advances, bonuses, deductions.
- Dashboard and a 30/60/90-day cash forecast.
- Reminders to clients (manual WhatsApp first, automatic later).
- One-way sync of won leads from the CRM.
- Attendance through Discord (detailed in the annex).
- Role-based access and an audit log.

**Out of scope (initial release)**

- Legal invoices (TVA, timbre, NIF/NIS/RC). Payment tracking only.
- Statutory payroll (CNAS, IRG).
- Foreign currencies.
- Online payment collection or bank integration.
- Double-entry accounting, balance sheet and tax reporting. Khazna keeps a cash ledger per account, which is not an accounting ledger.
- Mobile app.

### 1.4 Definitions

| Term | Meaning |
|---|---|
| Due (échéance) | A payment that must happen on a date, either money in or money out |
| Direction | `in` (we receive) or `out` (we pay) |
| Contract | The agreement that generates dues |
| Milestone | One payment step of a custom project (for example 40 / 30 / 30) |
| Period | The time slot a due covers (for example a renewal month, or a salary month) |
| Month-close | The action that creates one salary due per active employee for a month |
| Triage | The page that groups dues by urgency |
| MRR | Monthly recurring revenue |
| CCP, BaridiMob | Algerian payment methods |
| Algiers time | `Africa/Algiers` (UTC+1, no daylight-saving change) |
| Office | One of the company's locations (a city). Owns accounts, employees and contracts |
| Money account | A place where money is held: a cash box, a CCP account, a bank account |
| Ledger entry | One movement of money in or out of one account. Entries are never edited or deleted |
| Transfer | Money moved between two accounts. It is neither income nor expense |
| Reconciliation | Comparing an account's balance in Khazna with the real cash count or bank statement |
| Category | A label on costs and manual entries (hosting, rent, tools, taxes, fees…) used for reports |
| Method vs account | The **method** is how money moved (cash, CCP, BaridiMob, transfer). The **account** is where it went. BaridiMob is a method, because it operates a CCP account |

### 1.5 References

- `khazna-resume.md`
- `khazna-attendance.srs.md`
- CRM project `soft_client_suivi` (conventions, helpers, webhook pattern, `doc/refactor` backlog)

---

## 2. Overall description

### 2.1 Product perspective

Khazna is a standalone application, separate from the CRM, for two reasons: salaries must never be visible to CRM agents, and the CRM's payment model does not fit renewals, projects or money out. It shares the CRM's stack and conventions so helpers can be ported.

```
 CRM (leads) ──one-way sync (won lead → client)──> ┌──────────────────────────┐
                                                   │         Khazna           │
 Owner / finance / account managers ── panel ────> │  clients · contracts     │
                                                   │  dues · payments         │
 Employees ── Discord slash commands ────────────> │  payroll · attendance    │
                                                   │  triage · dashboard      │
 Clients <── WhatsApp reminders (via n8n, later) ─ └──────────────────────────┘
```

### 2.2 Core idea

**Everything is a due.** A due is a payment that must happen on a date. It is money in or money out, has an amount and a status, and can have several payments recorded against it. The kinds of dues differ only in how they are created.

| Kind | Direction | How dues are created |
|---|---|---|
| SaaS renewal | in | A contract with a cycle (monthly, quarterly, yearly) generates dues |
| Custom project | in | Milestones fixed at contract creation, due on a date or on a delivery event |
| Salary | out | Month-close creates one due per active employee |
| Other recurring cost | out | Same as SaaS contracts, reversed |

Dues say what **should** happen. Accounts and the ledger record what **did** happen, and where the money is.

### 2.3 User classes

| Role | Access |
|---|---|
| `owner` | Everything, including salaries, attendance of all employees, settings |
| `finance` | Everything except salaries (optional split). Reads approved hours |
| `account_manager` | Only receivables of their own clients |
| Employee (Discord only) | Own attendance through Discord. No panel access |
| System | Scheduler jobs, webhooks |

Any panel user can also be restricted to one **office**. A restricted user sees only the accounts, employees, clients, contracts and dues of that office.

### 2.4 Operating environment

- Laravel (current stable major), Inertia.js with Vue 3 and TypeScript, MySQL. The stack decision is explained in `doc/phases/README.md`.
- English interface (left-to-right), app brand **Khazna**. Client names and notes may contain Arabic script and must display correctly.
- Time stored in UTC, displayed in Algiers time.
- Private file storage for receipts.
- Laravel scheduler for daily jobs.
- Ported helpers from the CRM: `Money`, `AlgerianDate`, `Period` / `Metrics`, `Phone`.

### 2.5 Constraints

- Salary data must be unreachable by any role that lacks permission, including through direct URLs and Livewire calls.
- Money is never handled with floating-point numbers.
- Date logic uses Algiers day boundaries, never raw UTC dates.
- Discord requires a response to each interaction within 3 seconds and signs every request.

### 2.6 Assumptions and dependencies

- Single company, single currency (DA) at launch. A currency column is kept so other currencies can be added later.
- Expected scale: tens of employees, hundreds of contracts, thousands of dues. No special scaling work is planned.
- The CRM can send a webhook when a lead becomes won.
- Every employee has a Discord account in the company guild.
- n8n is available for WhatsApp automation in a later phase.

---

## 3. Functional requirements

Priority: **M** = must, **S** = should, **C** = could. Phase refers to section 9.

### 3.1 Authentication and roles

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-AUTH-01 | Users sign in with email and password. Accounts are created by the owner | M | 1 |
| FR-AUTH-02 | Each user has one role: `owner`, `finance` or `account_manager` | M | 1 |
| FR-AUTH-03 | Every resource, page and action is protected by a policy. No Livewire method runs without an authorization check | M | 1 |
| FR-AUTH-04 | `account_manager` sees only clients, contracts, dues and payments that belong to them | M | 1 |
| FR-AUTH-05 | Salary-related data (employees' salaries, salary dues, adjustments, payroll proposals) is visible to `owner` only, unless the owner grants it to `finance` in settings | M | 2 |
| FR-AUTH-06 | The owner can deactivate a user. Deactivated users cannot sign in, but their history stays | M | 1 |
| FR-AUTH-07 | Two-factor authentication for `owner` and `finance` | C | 4 |
| FR-AUTH-08 | A user can be restricted to one office (see FR-OFF-02) | M | 1 |

### 3.2 Parties (clients, employees, suppliers)

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-PTY-01 | Clients have name, contact person, phone, email, address, notes, an assigned account manager and an optional office | M | 1 |
| FR-PTY-02 | Phone numbers are validated and normalized with the `Phone` helper | M | 1 |
| FR-PTY-03 | Clients can be imported from a CSV file, with a preview and an error report before saving | M | 1 |
| FR-PTY-04 | Employees have name, phone, role title, hire date, base salary, status (active, inactive), Discord user ID, contract type and office | M | 2 |
| FR-PTY-05 | Suppliers (hosting, domains, tools, landlord) have name, contact and notes | S | 2 |
| FR-PTY-06 | A party with history cannot be hard-deleted. It is soft-deleted or deactivated | M | 1 |
| FR-PTY-07 | Each client page shows its contracts, open dues, payment history and total collected | S | 1 |

### 3.3 Contracts

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-CON-01 | A contract has a type (SaaS renewal, custom project, recurring cost), a party, a direction, an amount, a cycle (monthly, quarterly, yearly, one-time), start and end dates, status, auto-renew flag, office and (for costs) a category | M | 1 |
| FR-CON-02 | Contract status is `active`, `paused` or `canceled`. Only `active` contracts generate new dues | M | 1 |
| FR-CON-03 | Pausing or canceling a contract does not change paid dues. Open future dues are canceled with a recorded reason | M | 1 |
| FR-CON-04 | Changing the amount of a contract affects dues generated afterwards. Existing dues keep their amount unless edited explicitly | M | 1 |
| FR-CON-05 | A custom project contract holds a list of milestones: label, percentage or amount, due date or delivery trigger. The total must equal the contract amount | M | 2 |
| FR-CON-06 | A contract can have an attachment (signed PDF, quote) stored privately | S | 1 |
| FR-CON-07 | A contract near its end date appears in "Needs attention" until it is renewed, ended or canceled | S | 2 |
| FR-CON-08 | Contract history (amount, cycle and status changes) is recorded in the audit log | M | 1 |

### 3.4 Dues generation

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-DUE-01 | A daily command `dues:generate` creates dues for the next 30 days for every active contract | M | 1 |
| FR-DUE-02 | Generation is idempotent. The pair (contract, period) is unique, so running the command twice creates nothing new | M | 1 |
| FR-DUE-03 | A due has direction, contract, period, due date, amount due, amount paid and status | M | 1 |
| FR-DUE-04 | Status is `pending`, `partial`, `paid` or `canceled`. "Overdue" is not stored. It is derived when the due date has passed (Algiers day) and the due is `pending` or `partial` | M | 1 |
| FR-DUE-05 | Status is recalculated from the payments recorded whenever a payment is added, edited or removed | M | 1 |
| FR-DUE-06 | A due can be created manually (one-off cost or one-off income) without a contract | S | 1 |
| FR-DUE-07 | A due can be postponed to a new date. The old date, new date, reason, user and time are kept as history | M | 1 |
| FR-DUE-08 | A due can be canceled with a reason. Canceled dues do not count in any total | M | 1 |
| FR-DUE-09 | Month and cycle arithmetic follows calendar rules (a monthly contract starting on the 31st falls on the last day of shorter months) | M | 1 |

### 3.5 Payments

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-PAY-01 | A payment has a due, amount, date, **money account (required)**, method (cash, CCP, BaridiMob, bank transfer, other), reference, optional receipt file, notes and the user who recorded it | M | 1 |
| FR-PAY-02 | Partial payments are allowed. A due may have many payments | M | 1 |
| FR-PAY-03 | A payment cannot exceed the remaining amount unless the user explicitly confirms an overpayment, which is then shown as a credit on the due | S | 1 |
| FR-PAY-04 | Receipts (images, PDFs) are stored on a private disk and served only through an authorized route | M | 1 |
| FR-PAY-05 | A payment can be reversed only by the owner or finance, with a reason. The reversal is logged and the due status is recalculated | M | 1 |
| FR-PAY-06 | The payment date cannot be in the future | S | 1 |
| FR-PAY-07 | A client's credit or overpayment is shown but not applied automatically to other dues | C | 3 |

### 3.6 Triage page

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-TRI-01 | A page shows open dues in tabs: **Overdue, Today, This week, This month, Needs attention** | M | 1 |
| FR-TRI-02 | Each tab shows a count and a total amount | M | 1 |
| FR-TRI-03 | "Needs attention" shows a delivered milestone that has not been requested from the client, and an active contract with no next due | M | 2 |
| FR-TRI-04 | Filters: direction (in/out), kind (SaaS, project, salary, expense), client, office, assignee | M | 1 |
| FR-TRI-05 | Row action **Pay**: one click, the remaining amount prefilled, editable for partial payment | M | 1 |
| FR-TRI-06 | Row action **Postpone**: new date and reason | M | 1 |
| FR-TRI-07 | Row action **WhatsApp**: opens WhatsApp with a prefilled reminder for the client | M | 1 |
| FR-TRI-08 | Row action **Advance**, available on salary dues | M | 2 |
| FR-TRI-09 | An account manager sees only their own clients in this page | M | 1 |
| FR-TRI-10 | Tabs follow Algiers day boundaries and "This week" has a fixed definition (Saturday to Friday by default, configurable) | M | 1 |

### 3.7 Payroll

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-SAL-01 | The owner runs **Close month** for a chosen month. The system creates one salary due per active employee | M | 2 |
| FR-SAL-02 | Net salary = base + bonuses − deductions − advances already given for that month | M | 2 |
| FR-SAL-03 | Adjustments (advance, bonus, deduction) are recorded per employee per month with amount, reason and date. An advance also records the money account it was paid from and writes a ledger entry | M | 2 |
| FR-SAL-04 | Month-close is idempotent. Running it again for the same month does not create duplicate dues | M | 2 |
| FR-SAL-05 | Before month-close, a preview lists each employee with base, adjustments and net amount. The owner confirms | M | 2 |
| FR-SAL-06 | After a salary due is created, later adjustments to that month update the due while it is still unpaid. After payment they require an explicit correction by the owner | M | 2 |
| FR-SAL-07 | Salary due date is configurable (default the 5th of the following month) | M | 2 |
| FR-SAL-08 | A payslip (PDF) per employee per month | C | 4 |
| FR-SAL-09 | Hours from attendance feed month-close as proposals (see section 3.12) | M | 4 |

### 3.8 Project milestones

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-MIL-01 | Each milestone creates a due when the contract is created, with the date given or pending a delivery event | M | 2 |
| FR-MIL-02 | A milestone tied to a delivery event has no due date until marked **delivered**. Marking it delivered sets the due date (delivery date plus a payment term, default 0 days) | M | 2 |
| FR-MIL-03 | The user can record that the client was asked to pay (`requested_at`). A delivered milestone with no request appears in "Needs attention" | M | 2 |
| FR-MIL-04 | Changing the project amount after payments exist requires an explicit milestone rebalance, shown before saving | S | 2 |

### 3.9 Dashboard and forecast

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-DSH-01 | The dashboard shows: collected this month, outstanding, overdue, MRR, upcoming renewals (30 days), total salary bill | M | 2 |
| FR-DSH-02 | **Collected** = payments on `in` dues dated in the month. **Outstanding** = remaining amount of open `in` dues. **Overdue** = the part of outstanding past its due date. **MRR** = active recurring `in` contracts normalized to monthly (quarterly ÷ 3, yearly ÷ 12) | M | 2 |
| FR-DSH-03 | A **30 / 60 / 90-day forecast** shows expected money in against expected money out, by week, and the running balance | M | 2 |
| FR-DSH-04 | The forecast starts from the current balance of the selected accounts (all accounts, or the accounts of one office), and highlights the days when that balance goes below the next salary total of the same scope. Cash in one office cannot pay another office's salaries until it is transferred, so each office has its own view | M | 2 |
| FR-DSH-05 | A forecast figure can be opened to see the dues it is made of | S | 2 |
| FR-DSH-06 | Account managers see a dashboard of their own clients only. Salary figures are shown to the owner only | M | 2 |
| FR-DSH-07 | Revenue by client and by contract type, over a chosen period | C | 4 |
| FR-DSH-08 | Dashboard, triage and forecast can be filtered by office. A card lists the balance of each account | M | 2 |

### 3.10 Reminders

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-REM-01 | The WhatsApp row action opens a link with a message containing the client name, amount, due date and a payment instruction | M | 1 |
| FR-REM-02 | The message template is editable by the owner | S | 1 |
| FR-REM-03 | Reminders sent are logged against the due (who, when, template) | S | 1 |
| FR-REM-04 | Automatic reminders through n8n at J−7, J−1 and J+3 relative to the due date | S | 4 |
| FR-REM-05 | Automatic reminders stop when the due is paid, postponed, or canceled, and can be disabled per client | S | 4 |
| FR-REM-06 | Notification to the owner (Discord DM or panel) for dues becoming overdue and renewals approaching | C | 4 |

### 3.11 CRM integration

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-CRM-01 | Khazna starts standalone with clients imported from CSV | M | 1 |
| FR-CRM-02 | An endpoint receives a webhook from the CRM when a lead becomes **won** and creates a client | S | 4 |
| FR-CRM-03 | The endpoint verifies the `X-Webhook-Secret` header with a constant-time comparison | S | 4 |
| FR-CRM-04 | The sync is idempotent, keyed by the CRM lead ID. A repeated webhook updates nothing and creates no duplicate | S | 4 |
| FR-CRM-05 | Khazna never writes back to the CRM | S | 4 |

### 3.12 Attendance (Discord)

The full specification is in `khazna-attendance.srs.md`. This section lists its place in the system.

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-ATT-01 | Employees record work with `/check-in`, `/check-out`, `/tmp-check-out` in Discord. The system enforces one open session per employee | M | 3 |
| FR-ATT-02 | Sessions left open are closed automatically, flagged for review, and excluded from pay until approved | M | 3 |
| FR-ATT-03 | The owner can review, correct and approve sessions, with a reason and an audit trail | M | 3 |
| FR-ATT-04 | End-of-day report, reminders, daily digest and weekly summary are posted in Discord | S | 3 |
| FR-ATT-05 | At month-close, worked hours against expected hours produce **proposed** adjustments. Nothing becomes a deduction or a bonus without the owner's approval | M | 4 |
| FR-ATT-06 | Sessions can be tagged with a project, which allows a profitability report per contract (hours × cost per hour against the amount contracted) | S | 5 |
| FR-ATT-07 | Employees can request leave in Discord and the owner approves it | S | 5 |

### 3.13 Exports and documents

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-EXP-01 | Any table (dues, payments, contracts) can be exported to Excel with the filters applied | S | 4 |
| FR-EXP-02 | A client statement (PDF) lists dues and payments for a period | C | 4 |
| FR-EXP-03 | A monthly summary (PDF) shows money in, money out, outstanding and salary total | C | 4 |
| FR-EXP-04 | Exports respect the same permissions as the screens. Salary exports are owner-only | M | 4 |

### 3.14 Audit log and settings

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-AUD-01 | Creation, change and deletion of contracts, dues, payments, adjustments and users are logged with user, time, old and new values | M | 1 |
| FR-AUD-02 | The audit log is read-only and visible to the `owner` | M | 1 |
| FR-AUD-03 | Settings (salary due day, week start, reminder templates, opening balance, attendance options) are editable by the owner only | M | 1 |
| FR-AUD-04 | Setting changes are logged | S | 1 |

### 3.15 Offices and money accounts

The company has two offices in different cities, and each holds its own cash, CCP and bank accounts. The system must show where the money is, not only what is due.

**Offices**

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-OFF-01 | The owner can create and deactivate offices (name, city). At least one office exists | M | 1 |
| FR-OFF-02 | A user can be restricted to one office. A restricted user sees only that office's accounts, employees, clients, contracts and dues. An unrestricted user sees all offices | M | 1 |
| FR-OFF-03 | Each employee, contract and money account belongs to one office. A client may have an office | M | 1 |
| FR-OFF-04 | Lists and triage can be filtered by office (the dashboard and forecast filter is FR-DSH-08) | M | 1 |
| FR-OFF-05 | A report shows money in, money out and balance per office, including inter-office transfers | S | 2 |

**Accounts and ledger**

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-ACC-01 | A money account has a name, an office, a type (cash, CCP, bank, other), an opening balance with its date, and a status | M | 1 |
| FR-ACC-02 | An account's balance is its opening balance plus the sum of its ledger entries | M | 1 |
| FR-ACC-03 | Every payment goes into exactly one account and creates exactly one ledger entry, in the same transaction | M | 1 |
| FR-ACC-04 | Reversing a payment adds an opposite entry. Ledger entries are never edited or deleted | M | 1 |
| FR-ACC-05 | An entry dated before its account's opening date is refused | S | 1 |
| FR-ACC-06 | An account page shows a statement (date, description, in, out, running balance) with a date filter | M | 1 |
| FR-ACC-07 | A transfer moves money between two accounts, in the same or different offices, with date, optional fee and note. It writes an out entry and an in entry and is not counted as income or expense | M | 2 |
| FR-ACC-08 | A transfer between accounts of different offices is marked inter-office | S | 2 |
| FR-ACC-09 | A manual entry records money with no due (bank fees, taxes, petty cash, owner draws or injections) with account, category and description | M | 2 |
| FR-ACC-10 | Categories apply to costs and manual entries. A report shows money out by category and month | S | 2 |
| FR-ACC-11 | Reconciliation: the owner enters the real balance of an account on a date. The system shows the difference and can record it as an adjustment entry with a reason | M | 2 |
| FR-ACC-12 | An account can be deactivated only when its balance is zero | S | 2 |
| FR-ACC-13 | Balances and statements are visible to `owner` and `finance`. Payroll entries are shown to users without the salary permission as a single "Payroll" line | M | 2 |
| FR-ACC-14 | A daily check confirms every payment, advance, transfer and manual entry has its ledger entry, and every entry has exactly one source. A mismatch alerts the owner | S | 2 |

---

## 4. Data requirements

All tables use soft deletes. Money fields hold exact values, never floats. Timestamps are UTC.

| Entity | Main fields | Notes |
|---|---|---|
| `users` | name, email, password, role, active, office_id (nullable) | Panel users. A null office means all offices |
| `offices` | name, city, active | At least one |
| `clients` | name, contact, phone, email, address, notes, account_manager_id, office_id (nullable), crm_lead_id (nullable, unique) | `crm_lead_id` supports sync |
| `employees` | name, phone, title, hire_date, base_salary, status, discord_user_id, contract_type, hourly_rate, office_id, schedule fields | Salary fields owner-only |
| `suppliers` | name, contact, notes | Optional |
| `contracts` | type, direction, party (client, employee or supplier), amount, currency, cycle, starts_on, ends_on, status, auto_renew, office_id, category_id (costs), notes | Party is a polymorphic reference |
| `milestones` | contract_id, label, amount, due_on (nullable), trigger, delivered_at, requested_at | Custom projects |
| `dues` | direction, contract_id (nullable), milestone_id (nullable), party, period, due_on, amount_due, amount_paid, status, canceled_reason | Unique (contract_id, period) |
| `due_postponements` | due_id, old_due_on, new_due_on, reason, user_id | History |
| `payments` | due_id, account_id, amount, paid_on, method, reference, receipt_path, notes, recorded_by, reversed_at, reversal_reason | Receipts private |
| `salary_adjustments` | employee_id, month, type (advance, bonus, deduction), amount, reason, source (manual, attendance), approved_by, account_id and paid_on (advances only) | Source tracks attendance proposals |
| `reminders_log` | due_id, channel, template, sent_by, sent_at | Manual and automatic |
| `audit_logs` | user_id, entity, entity_id, action, old_values, new_values, created_at | Append only |
| `settings` | key, value | Owner-managed |
| `accounts` | office_id, name, type (cash, ccp, bank, other), currency, opening_balance, opening_date, active | Balance is never stored by hand. It is computed from entries |
| `account_entries` | account_id, direction, amount, entry_date, source_type, source_id, category_id (nullable), description, created_by | Append only. Exactly one source per entry |
| `transfers` | from_account_id, to_account_id, amount, fee, transfer_date, note, created_by | Two entries per transfer, plus one for the fee |
| `manual_entries` | account_id, direction, amount, entry_date, category_id, description, receipt_path | Money with no due |
| `categories` | name, kind (cost, income), active | For costs and manual entries |
| `reconciliations` | account_id, as_of, counted_balance, system_balance, difference, note, adjustment_entry_id (nullable), created_by | History per account |
| Attendance tables | sessions, breaks, edits, correction requests, holidays, leave requests, settings | See annex section 5 |

Key relationships: a client has many contracts. A contract has many dues. A due has many payments. An employee has many salary adjustments and many attendance sessions.

Key constraints:
- Unique (contract, period) on dues.
- A due's `amount_paid` always equals the sum of its non-reversed payments.
- A project's milestones add up to its contract amount.
- One salary due per employee per month.
- Every payment has exactly one ledger entry, and every ledger entry has exactly one source (payment, advance, transfer, manual entry or reconciliation adjustment).

---

## 5. External interfaces

| Interface | Direction | Description |
|---|---|---|
| Web app (Inertia + Vue) | Users ↔ Khazna | English, responsive for phone use |
| Discord interactions endpoint | Discord → Khazna | Slash commands, buttons, modals. Verified with Ed25519 signature |
| Discord REST API | Khazna → Discord | DMs, channel posts, digests |
| WhatsApp (`wa.me` link) | Khazna → browser | Prefilled reminder text, sent manually (phase 1) |
| n8n | Khazna ↔ n8n | Automatic reminders (phase 4) |
| CRM webhook | CRM → Khazna | Won lead creates a client. Header `X-Webhook-Secret` |
| File storage | Khazna ↔ private disk | Receipts, contract attachments |
| Scheduler | System | `dues:generate`, attendance auto-close, digests, reminders |

---

## 6. Business rules

| ID | Rule |
|---|---|
| BR-01 | Every payment obligation is represented by a due, in or out |
| BR-02 | A due is overdue when its date has passed in Algiers time and it is not fully paid or canceled |
| BR-03 | A due's paid amount is always derived from its payments, never typed in directly |
| BR-04 | Only active contracts generate new dues |
| BR-05 | Dues generation never creates two dues for the same contract and period |
| BR-06 | Net salary = base + bonuses − deductions − advances for that month |
| BR-07 | Month-close creates exactly one salary due per active employee per month |
| BR-08 | Hours from attendance never change pay automatically. The owner approves each proposed adjustment |
| BR-09 | Postponing, canceling, reversing and editing money always require a reason and are logged |
| BR-10 | An account manager can see only their own clients' receivables |
| BR-11 | Only the owner sees salary data, unless granted to finance |
| BR-12 | Monthly equivalent for MRR: monthly × 1, quarterly ÷ 3, yearly ÷ 12 |
| BR-13 | Data with history is never hard-deleted |
| BR-14 | All dates shown to the user are Algiers time. All day-based logic uses Algiers day boundaries |
| BR-15 | Every movement of money in or out of the company is one ledger entry on one account, written in the same transaction as the event that caused it |
| BR-16 | An account's balance is its opening balance plus the sum of its entries. Entries are never edited or deleted. A mistake is corrected with an opposite entry |
| BR-17 | A transfer moves money between accounts. It is neither income nor expense. A transfer fee is an expense on the source account |
| BR-18 | A payment's office is the office of the account it went into. A contract's office is where its revenue or cost is attributed. They may differ |
| BR-19 | A user restricted to an office sees only that office's accounts, employees, contracts and dues |
| BR-20 | Users without the salary permission see payroll entries only as one "Payroll" line in statements |

---

## 7. Non-functional requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Security | Policies on every action. Salary data protected on screens, exports, URLs and API calls |
| NFR-02 | Security | Receipts and attachments on a private disk, served by an authorized route only |
| NFR-03 | Security | Secrets (webhook secret, Discord token and public key) in environment variables only |
| NFR-04 | Security | Passwords hashed. Login rate-limited. Optional two-factor for owner and finance |
| NFR-05 | Integrity | Money operations run in database transactions. Totals are recomputed from payments, never trusted from the client |
| NFR-06 | Correctness | Money, period, cycle and metrics code has automated tests with hand-computed expected values (month-end dates, partial payments, Algiers day boundaries, forecast totals) |
| NFR-07 | Idempotency | Dues generation, month-close and CRM webhook can be repeated without duplicates |
| NFR-08 | Performance | The triage page and dashboard load in under 2 seconds at the expected scale. Discord interactions are acknowledged within 3 seconds |
| NFR-09 | Usability | English interface. The common action (pay a due) takes one click plus confirmation. Usable on a phone |
| NFR-10 | Auditability | Every money-related change is traceable to a user and a time |
| NFR-11 | Reliability | Daily database backup with a tested restore, and receipts included in the backup |
| NFR-12 | Maintainability | Same conventions, helpers and test style as the CRM. The lessons from the CRM refactor backlog (private receipts, policies, time boundaries, soft deletes) are applied from day one |
| NFR-13 | Privacy | Employees are told what attendance data is recorded and who can see it |
| NFR-14 | Availability | The panel is expected to run during business hours. Short maintenance windows are acceptable. A missed scheduled job must be recoverable by running it again |
| NFR-15 | Ledger integrity | Ledger entries are written in the same database transaction as their source. The app has no way to edit or delete an entry. A daily command verifies that every source has its entry and every entry has one source |

---

## 8. Key scenarios (acceptance)

| # | Given | When | Then |
|---|---|---|---|
| 1 | An active monthly SaaS contract of 10 000 DA | `dues:generate` runs twice on the same day | Exactly the due(s) for the next 30 days exist, no duplicates |
| 2 | A due of 10 000 DA | 4 000 DA is paid, then 6 000 DA | Status goes `partial` then `paid`. `amount_paid` is 10 000 DA |
| 3 | A due dated yesterday (Algiers), unpaid | The owner opens triage | It appears under Overdue |
| 4 | A due dated today at 00:30 Algiers (still the previous day in UTC) | The owner opens triage | It appears under Today, not Overdue |
| 5 | A due, then postponed by 10 days with a reason | The history is opened | Old date, new date, reason, user and time are shown |
| 6 | A monthly contract starting on January 31 | Dues are generated | February's due falls on the last day of February |
| 7 | A project of 100 000 DA with milestones 40 / 30 / 30 | A milestone is saved with 20 | Saving is refused: milestones must add up to the contract amount |
| 8 | A delivered milestone with no request recorded | The account manager opens triage | It appears under Needs attention |
| 9 | 3 active employees, salary due day on the 5th | The owner closes the month, then closes it again | One salary due per employee, no duplicates |
| 10 | An employee with base 60 000 DA, an advance of 10 000 DA and a bonus of 5 000 DA | Month-close | Salary due is 55 000 DA |
| 11 | An account manager signed in | They try to open a salary URL or another manager's client by direct link | Access denied |
| 12 | A receipt uploaded for a payment | An unauthorized user requests the file URL | Access denied |
| 13 | Expected inflow 200 000 DA and outflow 350 000 DA over 30 days, opening balance 100 000 DA | The forecast is opened | The balance is shown going negative and the day is highlighted |
| 14 | A won-lead webhook with a wrong secret | It reaches the endpoint | Rejected, nothing created |
| 15 | The same valid webhook arrives twice | Both processed | One client exists |
| 16 | An employee forgets to check out | The auto-close job runs | Session closed, flagged, not counted for pay until approved |
| 17 | Account "Cash A" with an opening balance of 100,000 DA | A 10,000 DA payment is recorded into it | Balance is 110,000 DA, and the ledger has one entry for the payment |
| 18 | The payment of scenario 17 | It is reversed | Balance returns to 100,000 DA. The ledger now has two entries (the original and the opposite), none deleted |
| 19 | "Cash A" holds 50,000 DA and "CCP B" holds 0 | A transfer of 20,000 DA with a 200 DA fee is recorded | Cash A is 29,800 DA, CCP B is 20,000 DA, the 200 DA fee is an expense, and the 20,000 DA is not counted as income or expense |
| 20 | A transfer between an account of office 1 and an account of office 2 | The office report is opened | It appears as an inter-office transfer, not as income or expense |
| 21 | A user restricted to office 1 | They open accounts, triage and the forecast, then try a direct link to an office 2 account | They see only office 1 data and the direct link is denied |
| 22 | Real cash count is 48,500 DA and Khazna shows 50,000 DA | The owner saves a reconciliation and records the difference | An adjustment entry of −1,500 DA with a reason exists and the balance is 48,500 DA |
| 23 | A finance user without the salary permission | They open a statement that includes salary payments | Salary payments appear as one "Payroll" line, with no employee names |
| 24 | Office 1 holds 80,000 DA and owes 120,000 DA of salaries on the 5th. Office 2 holds 500,000 DA | The forecast is filtered to office 1 | Office 1's balance is shown going negative, even though the company total is positive |

---

## 9. Phases

| Phase | Content | Done when |
|---|---|---|
| **1. MVP** | Authentication, roles, clients with CSV import, contracts, `dues:generate`, offices, money accounts and ledger, payments with private receipts, triage page, manual WhatsApp reminders, audit log, settings | You can run all receivables from Khazna instead of a spreadsheet, and see the balance of every account in each office |
| **2. Money out** | Employees, suppliers, recurring costs, project milestones, month-close, advances and adjustments, transfers, manual entries, categories, reconciliation, dashboard, per-office forecast | You see the cash picture of each office and can close a month's salaries |
| **3. Attendance** | Discord commands, auto-close, review and correction, end-of-day report, reminders, daily and weekly digest (annex phases 1–2) | The team uses `/check-in` daily and you trust the numbers |
| **4. Automation and payroll link** | Attendance feeding month-close, automatic WhatsApp reminders through n8n, CRM sync, Excel/PDF exports, payslips, optional two-factor | Month-close is prepared from approved hours with no spreadsheet |
| **5. Profitability and leave** | Project tagging, cost per hour, margin per contract, leave requests and balances (annex phases 4–5) | You know which contracts make money |

Phases 3 and 4 depend on phase 2, because attendance needs the employees table and month-close.

---

## 10. Open decisions

| # | Question | Default assumed if not answered |
|---|---|---|
| 1 | Who uses Khazna: only the owner, or also an accountant and account managers? | `owner`, `finance` and `account_manager` roles exist, all optional |
| 2 | Currency: DA only, or EUR/USD clients too? | DA only. Currency column kept for later |
| 3 | Legal invoices (TVA, timbre, NIF/NIS/RC) or payment tracking only? | Payment tracking only |
| 4 | Salaries: net amount plus adjustments, or CNAS/IRG deductions too? | Net amount plus adjustments |
| 5 | Existing CRM `subscriptions` and `payments` data: migrate or leave in the CRM? | Leave in the CRM as first-sale payments |
| 6 | Where does the forecast start from? | The balances of the money accounts. Each account gets an opening balance once, from a real count or statement |
| 7 | Salary due date and week start | The 5th of the following month. Week starts Saturday |
| 8 | Can finance see salaries? | No, owner only |
| 9 | Contract types and pay rules of the team (fixed, hourly, part-time, freelancer) | Fixed monthly and hourly |
| 10 | Is `/tmp-check-out` time paid? | Not paid |
| 11 | Do auto-closed attendance sessions count for pay before review? | No |
| 12 | Language of the interface and bot messages | English (decided). A second language can be added later |
| 13 | Payment terms for custom project milestones after delivery | 0 days (due on delivery) |
| 14 | Hosting and backup location | Not decided. To choose before launch |
| 15 | Which offices and accounts exist today (names, cities, cash, CCP, bank)? | Two offices, each with one cash account and one CCP or bank account |
| 16 | Does each office have its own clients and contracts, or are they shared? | Contracts carry an office. Clients may carry one. The owner sets both |
| 17 | Who manages each office's cash, and should they be restricted to their office? | Owner and finance see all offices. Other users can be restricted to one |
| 18 | Can finance see payroll lines in the ledger? | No. One masked "Payroll" line. Balances still reveal the payroll total, so this is a limited protection |
| 19 | Who pays a transfer fee, and are transfers between offices settled in any special way? | The fee is an expense on the source account. No settlement process |
| 20 | Do both offices share one Discord server? | Yes, with a separate attendance feed per office |
