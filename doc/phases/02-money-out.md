# Phase 2: Money out

| | |
|---|---|
| SRS phase | 2 |
| Effort | 3 weeks |
| Depends on | Phase 1 |
| Outcome | You see the cash picture, and you can close a month's salaries |
| Requirements | FR-AUTH-05, FR-PTY-04, 05, FR-CON-05, 07, FR-TRI-03, 08, FR-MIL-01..04, FR-SAL-01..07, FR-DSH-01..06 |

---

## 2.1 Schema changes

| Change | Detail |
|---|---|
| `suppliers` | name, contact, notes, soft deletes |
| `employees` | name, phone, title, hire_date, status, soft deletes. **No salary column here** (see below) |
| `employee_salary_history` | employee_id, amount, effective_from. The salary in force on a date is the latest row on or before it |
| `milestones` | contract_id, label, amount, due_on (nullable), trigger (date or delivery), delivered_at, requested_at |
| `salary_adjustments` | employee_id, month (`DATE`, first day), type (advance, bonus, deduction), amount, reason, paid_on and method (advances only), created_by |
| `dues` (altered) | add `employee_id` (nullable), `milestone_id` (nullable). Make `due_on` **nullable**, only allowed for a milestone waiting for delivery. Unique index on `(employee_id, period)` for salary dues |
| `settings` | add `opening_balance`, `opening_balance_date`, `salary_due_day` (already stored in phase 1) |

Keeping salary in a history table from the start means a raise never rewrites the past. Phase 5 (cost per hour) and any later report depend on this.

## 2.2 Suppliers and recurring costs

- [ ] Suppliers CRUD (FR-PTY-05).
- [ ] Enable contract types **recurring cost** (direction `out`, party a supplier) in the contract form. Same `GenerateDues` action, reversed direction.
- [ ] Contracts near their end date appear in "Needs attention" until renewed, ended or canceled (FR-CON-07).
- [ ] The triage page already filters by direction and kind. Check that out-dues display correctly (red amounts, "Pay" means money leaving).

## 2.3 Employees and salary protection

- [ ] Employees CRUD. Salary entered as "amount from date", which writes a `employee_salary_history` row.
- [ ] Two data classes: `EmployeeData` (no salary) and `EmployeeWithSalaryData`. The controller picks one based on the `view-salaries` gate (FR-AUTH-05).
- [ ] All payroll routes sit in one group behind `can:view-salaries`.
- [ ] **Leak test:** as `finance` and `account_manager`, request every page and the shared props. Assert the strings `salary`, `base_salary` and any salary amount never appear in the Inertia page JSON.
- [ ] The audit log shows salary changes only to the owner.

## 2.4 Project contracts and milestones

- [ ] Enable the **custom project** contract type.
- [ ] Milestone editor in the contract form: label and either a percentage or an amount. Amounts are computed with `Money::allocate`, and the total must equal the contract amount (FR-CON-05). Saving otherwise shows an error.
- [ ] On creation, one due per milestone (FR-MIL-01):
  - With a date: `due_on` set.
  - Delivery-triggered: `due_on` is null until delivery.
- [ ] **Mark delivered** action: sets `delivered_at` and `due_on = delivered_at + payment term` (default 0 days, setting) (FR-MIL-02).
- [ ] **Mark as requested** action: sets `requested_at` when the client has been asked to pay (FR-MIL-03).
- [ ] "Needs attention" tab now includes delivered milestones with no `requested_at`, and active contracts with no next due (FR-TRI-03).
- [ ] Rebalancing milestones after a payment exists shows a preview of the new amounts before saving (FR-MIL-04).
- [ ] Time-based tabs ignore dues with a null `due_on`. The contract page lists them as "waiting for delivery".

## 2.5 Payroll

**Adjustments**

- [ ] Add advance, bonus and deduction per employee per month (FR-SAL-03). Advances also record `paid_on` and `method`, because the money leaves on that day.
- [ ] **Advance** quick action on the employee page and on salary dues in triage (FR-TRI-08).

**Month-close** (`CloseMonth` action)

- [ ] Preview page for a chosen month. It writes nothing. For each active employee it shows: salary in force on the last day of the month, bonuses, deductions, advances, and the net amount (FR-SAL-05).
- [ ] Net = salary + bonuses − deductions − advances (FR-SAL-02).
- [ ] Confirm button runs `CloseMonth` in one transaction. It creates one due per employee with `kind = salary`, `period = YYYY-MM`, `due_on` = the configured salary due day of the following month (FR-SAL-01, 07).
- [ ] Idempotent: the unique `(employee_id, period)` index makes a second run skip existing dues. The result screen says which were created and which already existed (FR-SAL-04).
- [ ] `SyncSalaryDue` action keeps an unpaid salary due equal to the current adjustments. After payment, a change requires an explicit owner correction with a reason (FR-SAL-06).
- [ ] Employees who joined or left mid-month are **not prorated** automatically. The owner adds a deduction. This is an open question below.

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

- [ ] Each card group loads with Inertia deferred props, so the page appears at once and cards fill in.
- [ ] The salary card is only built if the user passes the gate.
- [ ] Indexes: `dues (direction, status, due_on)` and `payments (paid_on)`. Check query plans on realistic data.
- [ ] No caching at first. Add it only if a card is slower than 300 ms.

## 2.7 Forecast

- [ ] Opening balance and its date, entered by the owner in settings. Show a warning when the balance is older than 7 days (FR-DSH-04).
- [ ] 30 / 60 / 90-day view, bucketed by week. Per bucket: money in, money out, running balance.
- [ ] Rules:
  - In-dues in the window count at their remaining amount on their due date.
  - **Overdue out-dues** (for example unpaid salaries) count at day 0.
  - **Overdue in-dues** are listed separately and **not** counted by default, with a toggle to include them. This is the conservative choice. Confirm it (open question).
  - Dues with a null `due_on` are not counted.
- [ ] Highlight the weeks where the running balance falls below the next salary total.
- [ ] Click a bucket to list the dues it contains (FR-DSH-05).
- [ ] Chart readability was checked in phase 0. Confirm it with real data.

## 2.8 Role-aware views and hardening

- [ ] Account managers get a dashboard of their own clients only, without salary or out-money (FR-DSH-06).
- [ ] Finance sees everything except salary figures and salary dues. Check triage, forecast and exports exclude salary dues for finance. **The forecast must still include the salary outflow for the owner.**
- [ ] Extend the role matrix test with every new route.
- [ ] Review payroll terms and labels with someone who does payroll.

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

## Test plan

Feature and unit tests for the SRS scenarios **7, 8, 9, 10, 11, 13**, plus:

- `Metrics` on a hand-built dataset: monthly 10 000 DA + quarterly 30 000 DA + yearly 120 000 DA gives an MRR of 30 000 DA.
- Salary leak test (2.3).
- Month-close twice, month-close after an adjustment, month-close with an inactive employee.
- A salary raise effective mid-year does not change an already closed month.
- Forecast with overdue in-dues, overdue out-dues, a null `due_on`, and an old opening balance.
- Milestone totals with percentages that do not divide evenly (remainder goes to the last milestone).

## Exit checklist

- [ ] All listed requirements met and tests green.
- [ ] The leak test passes for every non-owner role.
- [ ] A real month is closed on staging and compared with the current payroll sheet, line by line.
- [ ] The forecast is compared with reality for two weeks.

## Open questions

1. Prorate salary for employees who join or leave mid-month, or always handle it by a manual deduction?
2. Are overdue receivables left out of the forecast by default (recommended)?
3. When a salary is raised mid-month, which amount applies (recommended: the amount in force on the last day of the month)?
4. Should an advance count as a cash outflow on its own dashboard line?
