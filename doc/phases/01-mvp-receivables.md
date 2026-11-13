# Phase 1: MVP receivables

| | |
|---|---|
| SRS phase | 1 |
| Effort | 3 to 4 weeks |
| Depends on | Phase 0 |
| Outcome | All money coming in is tracked in Khazna instead of a spreadsheet |
| Requirements | FR-AUTH-01..06, FR-PTY-01..03, 06, 07, FR-CON-01..04, 06, 08, FR-DUE-01..09, FR-PAY-01..06, FR-TRI-01, 02, 04..07, 09, 10, FR-REM-01..03, FR-CRM-01, FR-AUD-01..04 |

Scope note: in this phase the contract form offers only the **SaaS renewal** type. The other types exist in the enum but appear in phase 2.

---

## 1.1 Users and settings

- [ ] Migration: `users` (role, active), `settings` (key, value).
- [ ] Users screen (owner only): create, edit role, deactivate, reset password link.
- [ ] Settings screen (owner only): week start, reminder template, default currency label, salary due day (stored now, used in phase 2). Setting changes are audited (FR-AUD-04).
- [ ] Audit log screen (owner only): filter by user, entity, date. Read-only.

## 1.2 Clients

- [ ] Migration: `clients` (name, contact, phone, email, address, notes, account_manager_id, `crm_lead_id` nullable unique), soft deletes.
- [ ] CRUD with a `ClientIndexQuery` (search by name or phone, filter by account manager).
- [ ] Phone normalized on save through `Phone`. Invalid numbers are refused with a clear message.
- [ ] Soft delete only. A client with history cannot be hard-deleted (FR-PTY-06).
- [ ] Client page: contracts, open dues, payment history, total collected (FR-PTY-07).
- [ ] **CSV import** (FR-PTY-03), in three steps:
  1. Upload. Offer a downloadable template CSV.
  2. Preview: valid rows, invalid rows with line number and reason, possible duplicates (same phone).
  3. Confirm: insert in one transaction, show a summary.
- [ ] Import rules: UTF-8 required (reject other encodings with a message that explains how to re-save from Excel), file under 5 MB, header row mapped by name, account manager matched by email.

## 1.3 Contracts

- [ ] Migration: `contracts` (type, direction, client_id, amount, cycle, starts_on, ends_on, status, auto_renew, notes), soft deletes. Attachment stored on the private disk.
- [ ] Form for a SaaS renewal: client, amount, cycle (monthly, quarterly, yearly), start date, end date (optional), auto-renew.
- [ ] Status transitions: active ↔ paused → canceled. Cancelling or pausing cancels open future dues with a reason (FR-CON-03).
- [ ] Changing the amount affects only dues generated afterwards (FR-CON-04). Show a notice on the form saying so.
- [ ] Every change goes to the audit log (FR-CON-08).

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

## 1.5 Payments

- [ ] Migration: `payments` (due_id, amount, paid_on, method, reference, receipt_path, notes, recorded_by, reversed_at, reversal_reason), soft deletes.
- [ ] `RecordPayment` action, in one transaction:
  1. Lock the due (`lockForUpdate`).
  2. Validate the amount (positive, date not in the future).
  3. If it exceeds the remaining amount, require `confirm_overpayment`. The UI asks the user to confirm and resubmits.
  4. Insert the payment, recompute `amount_paid` from the sum of non-reversed payments, set the status.
- [ ] `ReversePayment` action (owner or finance, reason required). Recomputes the due.
- [ ] Receipts: image or PDF only, size limit, stored with a random name on the private disk. Served by `GET /payments/{payment}/receipt`, which checks the policy and streams the file with `Content-Type` fixed from the stored type, `X-Content-Type-Options: nosniff` and `Cache-Control: private` (FR-PAY-04).
- [ ] Invariant test: `amount_paid` always equals the sum of non-reversed payments, even after reversals.

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
- [ ] Filters: direction, kind, client, assignee. Stored in the URL.
- [ ] Row actions:
  - **Pay**: a dialog with the remaining amount prefilled and editable, method, date (today by default), reference, receipt.
  - **Postpone**: new date, reason.
  - **WhatsApp**: calls `POST /dues/{due}/reminders`, which logs the reminder and returns a `wa.me` link built from the normalized phone digits and the template. The browser then opens the link.
- [ ] Default template, editable in settings:
  `Hello {client}, this is a friendly reminder that {amount} for {contract} is due on {date}. Thank you.`
- [ ] Account managers only see their own clients (`visibleTo($user)` applied in the query object, and re-checked by the policy).
- [ ] Phone layout: dues as cards with the three actions reachable with one thumb.

## 1.7 Hardening

- [ ] `visibleTo($user)` scope on every query that returns clients, contracts, dues or payments. A test loops over every list route as an account manager and asserts no foreign record appears.
- [ ] Policy tests for every role × resource × action.
- [ ] Lazy-loading and N+1 check on triage, client page and contract page.
- [ ] Copy review of every message, label and validation error.
- [ ] Accessibility pass: keyboard focus in dialogs, labels on inputs, contrast.

## 1.8 Go-live

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
| `GET /users`, `/settings`, `/audit-log` | admin pages | owner |

## Test plan

Feature tests for the SRS scenarios **1, 2, 3, 4, 5, 6, 11, 12**, plus:

- Generating dues for a contract that is paused, ended, or has `auto_renew = false`.
- Two concurrent payments on one due do not both succeed past the remaining amount.
- An overpayment is refused without confirmation and accepted with it.
- CSV import: bad encoding, duplicate phones, a missing header, a row with an unknown account manager.
- A reversed payment returns the due to `pending` or `partial`.

## Exit checklist

- [ ] All listed requirements met and their tests green.
- [ ] Role matrix and scoping tests green.
- [ ] Two weeks of parallel run with matching totals.
- [ ] Backup restored on staging with production-like data.
- [ ] Real time spent compared with the estimate. Decision recorded about continuing with Inertia (see `README.md` section 1).

## Open questions

1. Are the triage tabs mutually exclusive (recommended), or does "This month" include today and this week?
2. Who is the "assignee" in the filter: the client's account manager, or a separate field on the due?
3. Should the WhatsApp template be set per client, since some clients may prefer Arabic or French? The template is editable text, so this is a settings question, not a code one.
