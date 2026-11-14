# Phase 4: Automation and payroll link

| | |
|---|---|
| SRS phase | 4 (annex phase 3) |
| Effort | 3 weeks |
| Depends on | Phases 2 and 3 |
| Outcome | Salaries are prepared from approved hours, reminders go out by themselves, exports and payslips work |
| Requirements | FR-ATT-05, FR-SAL-08, 09, FR-AUTH-07, FR-REM-04..06, FR-CRM-02..05, FR-EXP-01..04. From the annex: FR-AUTO-04, FR-PAY-01..06, FR-RPT-05 |

The five pieces below are independent of each other except 4.1 → 4.2 → 4.3. Build them in the order of value: payroll link first.

---

## 4.1 Holidays and expected time

- [ ] Migration: `holidays` (date, label). A screen to add and remove holidays.
- [ ] Islamic holidays follow the moon and are announced late, so **do not compute them**. Enter them by hand. A reminder to the owner each December to enter next year's list.
- [ ] `ExpectedTime` calculator for an employee and a month: working days (their `work_days`, default Sunday to Thursday) minus holidays minus approved leave, times `expected_minutes_per_day` (FR-PAY-06, BR-09).
- [ ] Hand-computed tests: November with 4 weekends, one holiday and 22 expected days gives 22 × 480 = 10 560 minutes.

## 4.2 Monthly attendance summary

`AttendanceSummary` for an employee and a month, using **approved sessions only**:

| Field | Definition |
|---|---|
| Worked minutes | Sum of session duration minus unpaid break minutes |
| Expected minutes | From 4.1 |
| Shortfall | `max(0, expected − worked)` |
| Overtime | `max(0, worked − expected)` |
| Late count | Check-ins after start + grace, on expected days |
| Absent days | Expected days with no approved session and no leave |

- [ ] The summary screen per employee and per month, with a link to every session behind a number.
- [ ] Excluded from the summary: `needs_review` sessions (FR-AUTO-04). They are listed as blockers.

## 4.3 Payroll proposals

Contract types drive the formula (FR-PAY-01, 03):

| Contract type | Proposal |
|---|---|
| Fixed monthly | A **deduction** proportional to the shortfall: `salary × shortfall ÷ expected`. Overtime produces nothing unless the owner turns on an overtime rate |
| Part-time | Same as fixed monthly, on the part-time expected minutes |
| Hourly or freelancer | The **base amount** = worked hours × hourly rate |

- [ ] Hand-computed examples to include as tests:
  - Salary 60 000 DA, expected 10 560 min, worked 10 080 min. Shortfall 480 min. Deduction = 60 000 × 480 ÷ 10 560 = **2 727,27 DA** (rounded half-up to the centime).
  - Hourly: 160 h × 800 DA = **128 000 DA**.
- [ ] Proposals are stored in `salary_adjustments` with `source = attendance` and `status = draft`. Statuses: draft, approved, rejected.
- [ ] Regeneration is idempotent: it replaces draft proposals of the same employee, month and source, and never touches approved or rejected ones.
- [ ] The owner reviews each proposal, may edit the amount (the original is kept in the audit log), then approves or rejects it. **Nothing is automatic** (BR-05, FR-ATT-05).
- [ ] Month-close (phase 2) now shows the proposals next to each employee. It is **blocked with a clear message** while the month has `needs_review` sessions or draft proposals that were never decided (FR-PAY-05). The message links to the sessions and proposals.
- [ ] Hourly employees have no fixed salary, so their month-close line comes entirely from approved hours.
- [ ] Finance sees hours and totals but not salary amounts, unless the owner changes that setting.

## 4.4 Automatic WhatsApp reminders (n8n)

Keep the **decision** in Khazna and only the **delivery** in n8n.

- [ ] A scheduled command finds dues needing a reminder at J−7, J−1 and J+3 (relative to `due_on`, Algiers day) and builds the message from the template.
- [ ] It sends one request per reminder to an n8n webhook, with a shared-secret header and an **idempotency key** `due_id:offset`. A repeated key does nothing.
- [ ] n8n sends the WhatsApp message and calls back `POST /api/reminders/{id}/status` (sent, failed) with the same secret. Khazna records the result in `reminders_log`.
- [ ] Reminders stop automatically when the due is paid, postponed or canceled, and can be switched off per client (FR-REM-05).
- [ ] Owner notification (Discord DM or in-app) for dues that become overdue and for renewals approaching (FR-REM-06).
- [ ] **Delivery channel risk:** unofficial WhatsApp gateways can get the company number banned. Prefer the official WhatsApp Business Cloud API with pre-approved message templates, which are required to message clients outside a 24-hour window.
- [ ] Test with n8n down: the reminder stays `pending`, retries with backoff, and never duplicates.

## 4.5 CRM sync (two repositories)

In **Khazna**:

- [ ] `POST /api/webhooks/crm/lead-won`, verifying `X-Webhook-Secret` with a constant-time comparison (FR-CRM-03).
- [ ] Creates a client from the payload. Idempotent on `crm_lead_id`: a repeat updates nothing and creates nothing (FR-CRM-04).
- [ ] Matches the account manager by email. If none matches, leaves it empty and flags the client.
- [ ] Never writes back to the CRM (FR-CRM-05).

In the **CRM** (separate repo, separate change):

- [ ] On lead status becoming won, dispatch a queued job that posts to Khazna with retries and backoff.
- [ ] Add the secret and the Khazna URL to its configuration.

Test with a wrong secret (rejected) and the same payload twice (one client).

## 4.6 Exports and documents

- [ ] **Excel:** the export uses the **same query object** as the screen, so a filtered list exports exactly what is shown (FR-EXP-01). Chunk large exports. Salary exports pass the gate (FR-EXP-04).
- [ ] **PDF spike first (half a day).** The layout is English and left-to-right, but client names and notes may be in Arabic script, and many PDF libraries do not shape Arabic letters correctly. Test a client statement that contains an Arabic client name with each candidate. A headless-Chrome based renderer is the most reliable. A lighter library is acceptable only if it renders the Arabic name correctly. Choose from what the spike shows.
- [ ] Client statement PDF: dues and payments for a period (FR-EXP-02).
- [ ] Monthly summary PDF: money in, money out, outstanding, salary total (owner only for the salary line) (FR-EXP-03).
- [ ] **Payslip PDF** per employee per month: salary, adjustments, attendance summary, net (FR-SAL-08). Generated on demand from stored data, with the same permissions as the salary pages.
- [ ] Attendance monthly export per employee (annex FR-RPT-05).
- [ ] **Ledger exports:** an account statement (Excel and PDF) for a date range, and the cash reports from phase 2 (money in and out by month and category, office report) as Excel. These exports are what an accountant will ask for. Payroll lines follow the same masking rule as the screens.

## 4.7 Two-factor authentication

- [ ] The starter kit's auth already includes optional two-factor. Add a middleware that **requires** it for `owner` and `finance`, and a grace period for existing users (FR-AUTH-07).
- [ ] Recovery codes screen and an owner-only way to reset a user's two-factor if they lose their device.

---

## Test plan

- SRS scenarios **14, 15, 16**, plus every example above.
- Proposal regeneration keeps approved decisions.
- Month-close blocked by a `needs_review` session, then allowed after approval.
- Reminder idempotency: the same key sent twice results in one message.
- Export respects filters and role. A finance user cannot export salary dues.
- PDF renders an Arabic client name correctly (visual check kept as a saved sample).

## Exit checklist

- [ ] One month's salaries are prepared from approved hours, and the totals match a manual calculation.
- [ ] A reminder cycle (J−7, J−1, J+3) ran on real dues with real delivery.
- [ ] A lead won in the CRM appears once in Khazna.
- [ ] Owner and finance have two-factor enabled.
- [ ] PDFs reviewed, including one with an Arabic client name.

## Open questions

1. Exact deduction rule for lateness and absence: proportional to the shortfall (recommended), a fixed amount per late arrival, or warnings only?
2. Is overtime paid, compensated with time, or ignored?
3. WhatsApp delivery: official Business API or another provider?
4. Should the owner be able to edit a proposal's amount, or only approve or reject it?
