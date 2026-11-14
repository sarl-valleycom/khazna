# Phase 2: Money out

| | |
|---|---|
| SRS phase | 2 |
| Effort | 4 weeks |
| Depends on | Phase 1 |
| Outcome | You see the cash picture, and you can close a month's salaries |
| Requirements | FR-AUTH-05, FR-PTY-04, 05, FR-CON-05, 07, FR-TRI-03, 08, FR-MIL-01..04, FR-SAL-01..07, FR-DSH-01..06, 08, FR-OFF-05, FR-ACC-07..14 |

---

## 2.1 Schema changes

| Change | Detail |
|---|---|
| `suppliers` | name, contact, notes, soft deletes |
| `employees` | name, phone, title, hire_date, status, soft deletes. **No salary column here** (see below) |
| `employee_salary_history` | employee_id, amount, effective_from. The salary in force on a date is the latest row on or before it |
| `milestones` | contract_id, label, amount, due_on (nullable), trigger (date or delivery), delivered_at, requested_at |
| `salary_adjustments` | employee_id, month (`DATE`, first day), type (advance, bonus, deduction), amount, reason, paid_on, method and `account_id` (advances only), created_by |
| `dues` (altered) | add `employee_id` (nullable), `milestone_id` (nullable). Make `due_on` **nullable**, only allowed for a milestone waiting for delivery. Unique index on `(employee_id, period)` for salary dues |
| `settings` | add the milestone payment term. `salary_due_day` is already stored from phase 1. There is **no manual opening balance**: the forecast starts from account balances |
| `categories` | name, kind (cost, income), active. Seed: hosting, domains, tools, rent, salaries, taxes, bank fees, other |
| `transfers` | from_account_id, to_account_id, amount, fee, transfer_date, note, created_by. Check: the two accounts differ |
| `manual_entries` | account_id, direction, amount, entry_date, category_id, description, receipt_path |
| `reconciliations` | account_id, as_of, counted_balance, system_balance, difference, note, adjustment_entry_id nullable, created_by |
| `contracts` (altered) | `category_id` required for recurring costs |

Keeping salary in a history table from the start means a raise never rewrites the past. Phase 5 (cost per hour) and any later report depend on this.

## 2.2 Suppliers and recurring costs

- [x] Suppliers CRUD (FR-PTY-05).
- [x] Categories screen (owner and finance). Recurring cost contracts require a category, and every due and payment of that contract inherits it (FR-ACC-10).
- [x] Enable contract types **recurring cost** (direction `out`, party a supplier) in the contract form. Same `GenerateDues` action, reversed direction.
- [x] Contracts near their end date appear in "Needs attention" until renewed, ended or canceled (FR-CON-07).
- [x] The triage page already filters by direction and kind. Check that out-dues display correctly (red amounts, "Pay" means money leaving).

## 2.3 Employees and salary protection

- [x] Employees CRUD. Salary entered as "amount from date", which writes a `employee_salary_history` row.
- [x] Two data classes: `EmployeeData` (no salary) and `EmployeeWithSalaryData`. The controller picks one based on the `view-salaries` gate (FR-AUTH-05).
- [x] All payroll routes sit in one group behind `can:view-salaries`.
- [x] **Leak test:** as `finance` and `account_manager`, request every page and the shared props. Assert the strings `salary`, `base_salary` and any salary amount never appear in the Inertia page JSON.
- [x] The audit log shows salary changes only to the owner.

## 2.4 Project contracts and milestones

- [x] Enable the **custom project** contract type.
- [x] Milestone editor in the contract form: label and either a percentage or an amount. Amounts are computed with `Money::allocate`, and the total must equal the contract amount (FR-CON-05). Saving otherwise shows an error.
- [x] On creation, one due per milestone (FR-MIL-01):
  - With a date: `due_on` set.
  - Delivery-triggered: `due_on` is null until delivery.
- [x] **Mark delivered** action: sets `delivered_at` and `due_on = delivered_at + payment term` (default 0 days, setting) (FR-MIL-02).
- [x] **Mark as requested** action: sets `requested_at` when the client has been asked to pay (FR-MIL-03).
- [x] "Needs attention" tab now includes delivered milestones with no `requested_at`, and active contracts with no next due (FR-TRI-03).
- [x] Rebalancing milestones after a payment exists shows a preview of the new amounts before saving (FR-MIL-04).
- [x] Time-based tabs ignore dues with a null `due_on`. The contract page lists them as "waiting for delivery".

## 2.5 Payroll

**Adjustments**

- [x] Add advance, bonus and deduction per employee per month (FR-SAL-03). An advance also records `paid_on`, `method` and the **account** it was paid from, and writes an out ledger entry through `LedgerWriter` in the same transaction, because the money leaves on that day.
- [x] Paying a salary due uses the normal Pay dialog: choose the account the money leaves. Each employee is paid from an account of their own office by default.
- [x] Ledger entries that come from salary dues or advances carry a payroll marker. Users without the salary permission see them in statements as one "Payroll" line, with no employee names (FR-ACC-13, BR-20). Balances still reveal the payroll total, which is why this is a limited protection (see open questions).
- [x] **Advance** quick action on the employee page and on salary dues in triage (FR-TRI-08).

**Month-close** (`CloseMonth` action)

- [x] Preview page for a chosen month. It writes nothing. For each active employee it shows: salary in force on the last day of the month, bonuses, deductions, advances, and the net amount (FR-SAL-05).
- [x] Net = salary + bonuses − deductions − advances (FR-SAL-02).
- [x] Confirm button runs `CloseMonth` in one transaction. It creates one due per employee with `kind = salary`, `period = YYYY-MM`, `due_on` = the configured salary due day of the following month (FR-SAL-01, 07).
- [x] Idempotent: the unique `(employee_id, period)` index makes a second run skip existing dues. The result screen says which were created and which already existed (FR-SAL-04).
- [x] `SyncSalaryDue` action keeps an unpaid salary due equal to the current adjustments. After payment, a change requires an explicit owner correction with a reason (FR-SAL-06).
- [x] Employees who joined or left mid-month are **not prorated** automatically. The owner adds a deduction. This is an open question below.

## 2.6 Dashboard

`Metrics` class, one method per number, each with hand-computed tests:

| Metric | Definition (FR-DSH-02) |
|---|---|
| Collected this month | Sum of non-reversed payments on `in` dues dated in the month |
| Outstanding | Sum of remaining amounts on open `in` dues |
| Overdue | The part of outstanding with `due_on < today` |
| MRR | Active recurring `in` contracts normalized to monthly: monthly × 1, quarterly ÷ 3, yearly ÷ 12 |
| Upcoming renewals | Contracts with a due in the next 30 days |
| Salary bill | Sum of salaries in force for active employees (owner only) |

- [x] Each card group loads with Inertia deferred props, so the page appears at once and cards fill in.
- [x] The office switcher (All offices, or one) applies to every card. A card lists each account's balance, grouped by office (FR-DSH-08).
- [x] The salary card is only built if the user passes the gate.
- [ ] Indexes: `dues (direction, status, due_on)` and `payments (paid_on)`. Check query plans on realistic data.
- [x] No caching at first. Add it only if a card is slower than 300 ms.

## 2.7 Forecast

- [x] Start from the **current balance of the selected accounts** (all accounts, or one office), not from a typed number (FR-DSH-04).
- [x] **Office view:** cash in one office cannot pay the other office's salaries until it is transferred. The forecast therefore uses an office's accounts against that office's employees' salaries and that office's contracts. Show the company total and each office side by side. A company total can look healthy while one office is short (scenario 24).
- [x] Warn when an included account has not been reconciled for more than 30 days, because its balance may be wrong.
- [x] 30 / 60 / 90-day view, bucketed by week. Per bucket: money in, money out, running balance.
- [x] Rules:
  - In-dues in the window count at their remaining amount on their due date.
  - **Overdue out-dues** (for example unpaid salaries) count at day 0.
  - **Overdue in-dues** are listed separately and **not** counted by default, with a toggle to include them. This is the conservative choice. Confirm it (open question).
  - Dues with a null `due_on` are not counted.
- [x] Highlight the weeks where the running balance falls below the next salary total.
- [x] Click a bucket to list the dues it contains (FR-DSH-05).
- [x] Chart readability was checked in phase 0. Confirm it with real data.

## 2.8 Role-aware views and hardening

- [ ] Account managers get a dashboard of their own clients only, without salary or out-money (FR-DSH-06).
- [ ] Finance sees everything except salary figures and salary dues. Check triage, forecast and exports exclude salary dues for finance. **The forecast must still include the salary outflow for the owner.**
- [ ] Extend the role matrix test with every new route.
- [ ] Review payroll terms and labels with someone who does payroll.

## 2.9 Transfers and manual entries

- [x] `RecordTransfer` action, in one transaction: an out entry on the source account, an in entry on the destination, and an out entry for the fee on the source (category "Bank fees") when there is one (FR-ACC-07). Transfers are not income or expense (BR-17).
- [x] A transfer between accounts of different offices is shown as **inter-office** in lists and in the office report (FR-ACC-08).
- [x] `RecordManualEntry` action for money with no due: bank fees, taxes, petty cash, owner draws and injections. It needs an account, a direction, a category and a description, with an optional receipt (FR-ACC-09).
- [x] Reversal of a transfer or a manual entry adds opposite entries with a reason. Owner and finance only.
- [x] Pages: transfers (list and create), manual entries (list and create). Both also appear in the account statement.
- [x] Deactivating an account requires a zero balance (FR-ACC-12).

## 2.10 Reconciliation

- [x] Screen: choose an account and a date, enter the counted cash or the statement balance. Khazna shows its own balance at the end of that date and the difference (FR-ACC-11).
- [x] Saving with a difference offers two choices: **record the difference** (an adjustment entry with a required reason, category "Reconciliation difference") or **leave it open** to investigate.
- [x] History per account, and a reminder when an account has not been reconciled for 30 days (cash accounts: weekly is better).
- [x] `ledger:verify` runs daily: every payment, advance, transfer and manual entry has exactly one set of entries, and every entry has one source. A mismatch alerts the owner (FR-ACC-14, NFR-15).

## 2.11 Cash reports

- [ ] **Money in and out by month and by category**, filtered by office or account (FR-ACC-10, FR-OFF-05).
- [ ] **Office report:** money in, money out, balance, and inter-office transfers sent and received.
- [ ] A clearly labelled **cash result** (money in minus money out). It is not an accounting profit, because it ignores unpaid dues and non-cash items.
- [ ] Excel export of these reports arrives in phase 4.

---

## Routes and pages

| Route | Page | Roles |
|---|---|---|
| `/suppliers` | `suppliers/index`, `form` | owner, finance |
| `/employees` | `employees/index`, `show` | owner (with salary), finance (without) |
| `/payroll/adjustments` | `payroll/adjustments` | owner |
| `/payroll/close` (preview and confirm) | `payroll/close` | owner |
| `/contracts/{id}/milestones/{m}/deliver`, `/request` | actions | owner, finance, account manager (scoped) |
| `/dashboard` | `dashboard/index` | all (scoped) |
| `/forecast` | `forecast/index` | owner, finance (salary outflow shown to owner) |
| `/accounts/transfers`, `/accounts/entries` | `accounts/transfers`, `accounts/entries` | owner, finance (scoped by office) |
| `/accounts/reconcile` | `accounts/reconcile` | owner, finance |
| `/reports/cash` | `reports/cash` | owner, finance (scoped by office) |
| `/categories` | `categories/index` | owner, finance |

## Test plan

Feature and unit tests for the SRS scenarios **7, 8, 9, 10, 11, 13, 19, 20, 22, 23, 24**, plus:

- `Metrics` on a hand-built dataset: monthly 10 000 DA + quarterly 30 000 DA + yearly 120 000 DA gives an MRR of 30 000 DA.
- Salary leak test (2.3).
- Month-close twice, month-close after an adjustment, month-close with an inactive employee.
- A salary raise effective mid-year does not change an already closed month.
- Forecast with overdue in-dues, overdue out-dues, a null `due_on`, and an account not reconciled for over a month.
- Transfer with a fee: both account balances and the fee entry add up to zero change in the company total, except the fee.
- A transfer cannot use the same account twice, and a reversed transfer restores both balances.
- `ledger:verify` detects a payment whose ledger entry was removed directly in the database.
- A salary payment appears as a masked "Payroll" line for a finance user, and as a named line for the owner.
- An advance writes exactly one out entry on the chosen account, and reduces that month's net salary.
- Milestone totals with percentages that do not divide evenly (remainder goes to the last milestone).

## Exit checklist

- [ ] All listed requirements met and tests green.
- [ ] The leak test passes for every non-owner role.
- [ ] A real month is closed on staging and compared with the current payroll sheet, line by line.
- [ ] The forecast is compared with reality for two weeks.
- [ ] Every account is reconciled once against a real count or statement, and `ledger:verify` runs clean.

## Open questions

1. Prorate salary for employees who join or leave mid-month, or always handle it by a manual deduction?
2. Are overdue receivables left out of the forecast by default (recommended)?
3. When a salary is raised mid-month, which amount applies (recommended: the amount in force on the last day of the month)?
4. Can finance see payroll lines in the ledger? Recommended: no, shown as one "Payroll" line. Balances still reveal the payroll total, so this is a limited protection.
5. Who pays a transfer fee: the source account (recommended) or the destination?
6. Are transfers between offices settled or reported in any special way, beyond being flagged inter-office?
7. Should each office have its own cash result report, with revenue attributed by contract office and costs by account office?
