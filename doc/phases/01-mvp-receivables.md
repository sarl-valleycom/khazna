# Phase 1: MVP receivables

| | |
|---|---|
| SRS phase | 1 |
| Effort | 4 to 5 weeks (about one week of it is offices, accounts and the ledger) |
| Depends on | Phase 0 |
| Outcome | All money coming in is tracked in Khazna instead of a spreadsheet, and you see the balance of every account in each office |
| Requirements | FR-AUTH-01..06, 08, FR-OFF-01..04, FR-ACC-01..06, FR-PTY-01..03, 06, 07, FR-CON-01..04, 06, 08, FR-DUE-01..09, FR-PAY-01..06, FR-TRI-01, 02, 04..07, 09, 10, FR-REM-01..03, FR-CRM-01, FR-AUD-01..04 |

Scope note: in this phase the contract form offers only the **SaaS renewal** type. The other types exist in the enum but appear in phase 2.

---

## 1.1 Users, offices and settings

- [x] Migration: `offices` (name, city, active), `users` (role, active, `office_id` nullable), `settings` (key, value).
- [x] Offices screen (owner only): create and deactivate. At least one office must exist. Seed the two real offices (FR-OFF-01).
- [x] The user form has an optional office. A user with an office sees only that office's data (FR-OFF-02, FR-AUTH-08). The check lives in the shared `visibleTo($user)` scope from phase 0.
- [x] Office switcher in the app layout for unrestricted users (FR-OFF-04).
- [x] Users screen (owner only): create, edit role, deactivate, reset password link.
- [x] Settings screen (owner only): week start, reminder template, default currency label, salary due day (stored now, used in phase 2). Setting changes are audited (FR-AUD-04).
- [x] Audit log screen (owner only): filter by user, entity, date. Read-only.

Notes:
- `offices` and `settings` are key/value tables; `users.office_id` stays nullable and indexed. The office switcher writes the `office` query parameter and is hidden for restricted users. The domain queries consume that filter in later sections.
- `DemoSeeder` now seeds `Algiers HQ` and `Oran Office` plus default settings, then the four demo users. The office names are placeholders until the owner confirms the real office names; real accounts and opening balances remain out of scope (open question 4).
- Deactivating the last active office is refused. Deactivating yourself or removing the last active owner is also refused.
- The owner settings screen stores the week start, reminder template, currency label and salary due day. Every setting write is audited.
- 1.1 is covered by `tests/Feature/Phase1/OfficesUsersSettingsTest.php` and the owner Playwright admin smoke test.

## 1.2 Clients

- [x] Migration: `clients` (name, contact, phone, email, address, notes, account_manager_id, `office_id` nullable, `crm_lead_id` nullable unique), soft deletes.
- [x] CRUD with a `ClientIndexQuery` (search by name or phone, filter by account manager).
- [x] Phone normalized on save through `Phone`. Invalid numbers are refused with a clear message.
- [x] Soft delete only. A client with history cannot be hard-deleted (FR-PTY-06).
- [ ] Client page: contracts, open dues, payment history, total collected (FR-PTY-07).
- [x] **CSV import** (FR-PTY-03), in three steps:
  1. Upload. Offer a downloadable template CSV.
  2. Preview: valid rows, invalid rows with line number and reason, possible duplicates (same phone).
  3. Confirm: insert in one transaction, show a summary.
- [x] Import rules: UTF-8 required (reject other encodings with a message that explains how to re-save from Excel), file under 5 MB, header row mapped by name, account manager matched by email.

Notes:
- Phones are stored as `213XXXXXXXXX` and shown as `0XXX XX XX XX`. `ClientIndexQuery` supports name/phone search, account-manager filter, the shared office filter and whitelisted sorting.
- An account manager creates clients for themselves and their office; a client's office must match its manager's office. Duplicate phones are not unique at the database level but are flagged and skipped during import.
- Import is owner/finance only. The preview is cached for 30 minutes and bound to the user; confirm inserts valid rows in one transaction. Duplicates against existing (including soft-deleted) clients and within the file are skipped. Commas, semicolons and tabs are accepted; UTF-8 BOM is accepted.
- The client page currently shows the profile and its contracts. Open dues, payments and total collected are wired when those models arrive in 1.4 and 1.5 (FR-PTY-07 remains open).
- Covered by `tests/Feature/Phase1/ClientsTest.php` and the client Playwright import-preview smoke test. `DemoSeeder` adds three demo clients.

## 1.3 Contracts

- [x] Migration: `contracts` (type, direction, client_id, `office_id` required, amount, cycle, starts_on, ends_on, status, auto_renew, notes), soft deletes. Attachment stored on the private disk.
- [x] Form for a SaaS renewal: client, amount, cycle (monthly, quarterly, yearly), start date, end date (optional), auto-renew.
- [x] Status transitions: active ↔ paused → canceled, with a required reason for pause/cancel and an audit trail.
- [ ] Cancelling or pausing cancels open future dues with a reason (FR-CON-03). Wired in 1.4 when dues exist.
- [x] Changing the amount affects only dues generated afterwards (FR-CON-04). Show a notice on the form saying so.
- [x] Every change goes to the audit log (FR-CON-08).

Notes:
- Phase 1 stores only SaaS renewals with direction `in`; the form fixes type and direction, and creates contracts as `active`. The other contract types remain Phase 2.
- Clients are immutable on an existing contract. Contract offices are required; an unrestricted owner/finance user chooses one, everyone else is fixed to their own office. The client's office is the default.
- Account managers see contracts only for their own clients and their office. Office-restricted owner/finance users see contracts attributed to their office.
- A contract attachment accepts PDF, JPG, PNG or WebP up to 10 MB. It is stored on the private `local` disk and streamed with a fixed content type and `nosniff`.
- Amount updates never touch existing dues (none exist yet); the 1.4 generator will read the current amount for future periods only.
- The client page lists its visible contracts. Open dues, payment history and total collected remain for 1.4/1.5.
- Covered by `tests/Feature/Phase1/ContractsTest.php`, the client-page contract test and the contract Playwright create flow. `DemoSeeder` adds three demo contracts.

## 1.4 Dues engine

- [ ] Migration: `dues` (kind, direction, contract_id nullable, client_id, period, due_on, amount_due, amount_paid, status, canceled_reason), soft deletes.
  - Unique index on `(contract_id, period)`.
  - Indexes on `(status, due_on)` and `(direction, due_on)`.
- [ ] Migration: `due_postponements` (due_id, old_due_on, new_due_on, reason, user_id).
- [ ] `GenerateDues` action:
  - For each active contract, compute every due date between `max(starts_on, today)` and `today + 30 days`, anchored on the start date's day of the month, using the cycle arithmetic from phase 0.
  - Respect `ends_on` and `auto_renew = false`.
  - Insert with `insertOrIgnore` (or an upsert) on the unique key, so a second run creates nothing.
  - Return a report: created, skipped, contracts touched.
- [ ] `dues:generate` command, scheduled daily at 01:00 Algiers time with `withoutOverlapping()`. Log the report.
- [ ] Also run the action for one contract as soon as it is created or reactivated, so the user does not wait until the night.
- [ ] Status rules (FR-DUE-04, 05): `pending`, `partial`, `paid`, `canceled` are stored. "Overdue" is computed from `due_on < AlgerianDate::today()` on open dues, never stored.
- [ ] Manual due (one-off, without a contract) (FR-DUE-06).
- [ ] `PostponeDue` action (new date + reason, history row) and `CancelDue` action (reason).

## 1.5 Accounts, ledger and payments

**Accounts and ledger** (FR-ACC-01..06)

- [ ] Migration: `accounts` (office_id, name, type: cash, ccp, bank, other, currency, opening_balance, opening_date, active).
- [ ] Migration: `account_entries` (account_id, direction, amount, entry_date, source_type, source_id, category_id nullable, description, created_by). Append only: no update or delete route, and the model refuses both.
- [ ] A `LedgerWriter` service is the **only** code that inserts entries. Actions call it inside their own transaction. It refuses an entry dated before the account's opening date (FR-ACC-05).
- [ ] Balance = opening balance + sum of entries, calculated by a query. Nothing stores a balance by hand.
- [ ] Accounts screen: balances grouped by office, plus a per-account statement with running balance and a date filter (FR-ACC-06). Excel export arrives in phase 4.
- [ ] Method (cash, CCP, BaridiMob, bank transfer, other) stays on the payment as the way the money moved. The **account** says where it went.

**Payments**

- [ ] Migration: `payments` (due_id, `account_id` required, amount, paid_on, method, reference, receipt_path, notes, recorded_by, reversed_at, reversal_reason), soft deletes.
- [ ] `RecordPayment` action, in one transaction:
  1. Lock the due (`lockForUpdate`).
  2. Validate the amount (positive, date not in the future).
  3. If it exceeds the remaining amount, require `confirm_overpayment`. The UI asks the user to confirm and resubmits.
  4. Insert the payment and its ledger entry on the chosen account, recompute `amount_paid` from the sum of non-reversed payments, set the status.
- [ ] `ReversePayment` action (owner or finance, reason required). Recomputes the due and adds an **opposite ledger entry**. The original entry stays.
- [ ] Receipts: image or PDF only, size limit, stored with a random name on the private disk. Served by `GET /payments/{payment}/receipt`, which checks the policy and streams the file with `Content-Type` fixed from the stored type, `X-Content-Type-Options: nosniff` and `Cache-Control: private` (FR-PAY-04).
- [ ] Invariant test: `amount_paid` always equals the sum of non-reversed payments, even after reversals.
- [ ] Invariant test: every payment has exactly one ledger entry, and an account's balance equals its opening balance plus its entries, including after reversals.

## 1.6 Triage page (the home screen)

Query object `DueTriageQuery`, with `today = AlgerianDate::today()`:

| Tab | Rule on open dues |
|---|---|
| Overdue | `due_on < today` |
| Today | `due_on = today` |
| This week | `today < due_on ≤ end of week` |
| This month | `end of week < due_on ≤ end of month` |
| Needs attention | Filled in phase 2 (milestones). In phase 1 it shows active contracts with no next due |

The tabs are **mutually exclusive** so their counts add up. Confirm this reading of the SRS (see open questions).

- [ ] Counts and totals for all tabs from one grouped query.
- [ ] Filters: direction, kind, client, office, assignee. Stored in the URL.
- [ ] Row actions:
  - **Pay**: a dialog with the remaining amount prefilled and editable, **account** (defaults to the last account used for this client, and only lists accounts the user may see), method, date (today by default), reference, receipt.
  - **Postpone**: new date, reason.
  - **WhatsApp**: calls `POST /dues/{due}/reminders`, which logs the reminder and returns a `wa.me` link built from the normalized phone digits and the template. The browser then opens the link.
- [ ] Default template, editable in settings:
  `Hello {client}, this is a friendly reminder that {amount} for {contract} is due on {date}. Thank you.`
- [ ] Account managers only see their own clients (`visibleTo($user)` applied in the query object, and re-checked by the policy).
- [ ] Phone layout: dues as cards with the three actions reachable with one thumb.

## 1.7 Hardening

- [ ] `visibleTo($user)` scope on every query that returns clients, contracts, dues or payments. A test loops over every list route as an account manager and asserts no foreign record appears.
- [ ] Policy tests for every role × resource × action.
- [ ] Office scope test: as a user restricted to office 1, open every list and try direct links to office 2 accounts, contracts, clients, dues and payments. All must be hidden or denied.
- [ ] Lazy-loading and N+1 check on triage, client page and contract page.
- [ ] Copy review of every message, label and validation error.
- [ ] Accessibility pass: keyboard focus in dialogs, labels on inputs, contrast.

## 1.8 Go-live

- [ ] Create the offices and every real account. **Count the cash and read each CCP and bank balance on one chosen date**, and enter them as opening balances with that date.
- [ ] Import the real client list from CSV.
- [ ] Enter the active SaaS contracts.
- [ ] Run **in parallel with the spreadsheet for two weeks**. Compare outstanding and overdue totals weekly.
- [ ] Verify a backup restore with real data.
- [ ] Train account managers (30 minutes: find a client, record a payment, send a reminder).

---

## Routes and pages

| Route | Page | Roles |
|---|---|---|
| `GET /` (triage) | `triage/index` | all |
| `GET /clients`, `/clients/{id}`, `/clients/import` | `clients/index`, `show`, `import` | all (scoped) |
| `GET /contracts`, `/contracts/{id}` | `contracts/index`, `show` | owner, finance, account manager (scoped) |
| `POST /dues/{due}/payments` | dialog | owner, finance, account manager (scoped) |
| `POST /dues/{due}/postpone` | dialog | owner, finance, account manager (scoped) |
| `POST /dues/{due}/reminders` | returns link | all (scoped) |
| `POST /payments/{payment}/reverse` | dialog | owner, finance |
| `GET /payments/{payment}/receipt` | file | by policy |
| `GET /users`, `/settings`, `/audit-log`, `/offices` | admin pages | owner |
| `GET /accounts`, `/accounts/{id}` | `accounts/index`, `show` (statement) | owner, finance (scoped by office) |

## Test plan

Feature tests for the SRS scenarios **1, 2, 3, 4, 5, 6, 11, 12, 17, 18, 21**, plus:

- Generating dues for a contract that is paused, ended, or has `auto_renew = false`.
- Two concurrent payments on one due do not both succeed past the remaining amount.
- An overpayment is refused without confirmation and accepted with it.
- CSV import: bad encoding, duplicate phones, a missing header, a row with an unknown account manager.
- A reversed payment returns the due to `pending` or `partial`.
- A payment dated before the account's opening date is refused.
- A payment into an account the user cannot see is refused.
- Trying to update or delete a ledger entry through the model fails.

## Exit checklist

- [ ] All listed requirements met and their tests green.
- [ ] Role matrix and scoping tests green.
- [ ] Two weeks of parallel run with matching totals.
- [ ] Backup restored on staging with production-like data.
- [ ] After two weeks, each account's balance in Khazna matches a real cash count or bank statement.
- [ ] Real time spent compared with the estimate. Decision recorded about continuing with Inertia (see `README.md` section 1).

## Open questions

1. Are the triage tabs mutually exclusive (recommended), or does "This month" include today and this week?
2. Who is the "assignee" in the filter: the client's account manager, or a separate field on the due?
3. Should the WhatsApp template be set per client, since some clients may prefer Arabic or French? The template is editable text, so this is a settings question, not a code one.
4. Which accounts exist in each office today (cash box, CCP, bank, others), and does each office have a default account for incoming payments?
5. Do contracts always belong to the office that sold them, or can an office collect money for the other office's contract? (The design allows both: a payment's office comes from its account, and a contract's office is where revenue is attributed.)
