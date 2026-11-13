# Phase 5: Profitability and leave

| | |
|---|---|
| SRS phase | 5 (annex phases 4 and 5) |
| Effort | 2 weeks |
| Depends on | Phases 3 and 4 |
| Outcome | You know which contracts make money, and absences are handled inside the system |
| Requirements | FR-ATT-06, FR-ATT-07. From the annex: FR-PRJ-01..05, FR-LEV-01..04 |

---

## 5.1 Project tagging

- [ ] Migration: `attendance_segments` (session_id, contract_id nullable, started_at, ended_at). Every session gets a first segment when it starts. Backfill existing sessions with one untagged segment each.
- [ ] `/check-in` gains an optional `project` option with **autocomplete**. Discord autocomplete returns at most 25 choices, so search active contracts by client or contract name as the user types. Send the contract ID as the choice value.
- [ ] `/switch project:<…>` closes the current segment and opens a new one without ending the session (FR-PRJ-02). Breaks that fall inside a segment are removed from that segment's minutes.
- [ ] Closing a session closes its last segment. Unique generated-column protection (as in phase 3) so a session never has two open segments.
- [ ] Untagged time is reported as **unassigned**, never hidden (FR-PRJ-05). A weekly note in the digest shows each person's unassigned share, so tagging habits improve.
- [ ] One dropdown is the only extra step. If staff find it a burden, allow a default project per employee.

## 5.2 Cost per hour

- [ ] `CostPerHour` calculator for an employee and a month:
  - Fixed or part-time: salary in force that month (from `employee_salary_history`) ÷ expected monthly hours (from phase 4).
  - Hourly: the hourly rate.
- [ ] Hand-computed example: 60 000 DA ÷ 176 expected hours = 340,91 DA per hour. 120 hours on a project cost 40 909,09 DA.
- [ ] Only the owner can see cost per hour, because it exposes salaries.
- [ ] Decision to record in the screen's help text: cost is salary only. It does not include employer charges, equipment, rent or tools.

## 5.3 Profitability report

Per contract (FR-PRJ-04), with a period filter:

| Column | Definition |
|---|---|
| Hours | Sum of segment minutes (without breaks) on the contract |
| Labor cost | Sum of hours × the employee's cost per hour for that month |
| Revenue | Switch between **contracted** (the contract or milestone amounts in the period) and **collected** (payments received) |
| Margin | Revenue − labor cost, and as a percentage |

- [ ] Views: by contract, by client, by month, and by employee (which contracts they spend time on).
- [ ] Recurring SaaS contracts show a monthly margin trend. Custom projects show cumulative margin against the budget, with a warning when hours already cost more than the amount collected.
- [ ] Optional: link a supplier cost contract (for example hosting) to the SaaS contract it serves, to show **direct cost** in the margin (open question).
- [ ] Hand-computed test: a 100 000 DA project, 120 h at 340,91 DA gives a margin of 59 090,91 DA (59.1%).
- [ ] Owner-only page. The chart uses the same chart components as the dashboard.

## 5.4 Leave

- [ ] `/leave` with options: `type` (choices: paid, unpaid, sick), `from` and `to` (text, `YYYY-MM-DD`), `reason`. Discord has no date picker, so validate the dates and answer with a clear error and an example when they are wrong.
- [ ] The request is posted to the owner's channel with **Approve** and **Reject** buttons (FR-LEV-02). The decision updates the request, edits the message, and notifies the employee by DM.
- [ ] Approved leave feeds `ExpectedTime` (phase 4): those days are not expected, no reminders are sent, and no absence is counted (FR-LEV-03).
- [ ] Unpaid leave also reduces pay through the phase 4 proposal, because those days are removed from expected time and the salary is paid for the days that remain. Confirm this rule (open question).
- [ ] Optional leave balance per employee per year, with an allowance per type (FR-LEV-04).
- [ ] Owner page: leave calendar and a list of pending requests. Overlaps with other employees' leave are flagged.
- [ ] The owner can also create leave for an employee from the web app (illness reported by phone).

## 5.5 Hardening

- [ ] Role and leak tests for the new pages and for cost-per-hour data in props.
- [ ] Performance check of the profitability queries on a year of data. Add indexes on `attendance_segments (contract_id, started_at)`.
- [ ] Copy review of the leave flow with a staff member.

---

## Test plan

- Segment splitting: a session with two projects and a break in the second.
- `/switch` when no session is open, and when already on that project.
- Cost-per-hour examples above, and a mid-year raise (earlier months keep the old rate).
- Profitability with revenue basis switched between contracted and collected.
- Leave overlapping a holiday, a weekend, and an existing session.
- Role matrix and leak tests.

## Exit checklist

- [ ] At least one month of real project tagging.
- [ ] The report reviewed against what you expected for two or three known contracts.
- [ ] Leave requests used by the team for a full month.

## Open questions

1. Should hosting and tool costs be linked to the contract they serve, to show direct cost in the margin?
2. Is unpaid leave deducted proportionally from the fixed salary?
3. Default project per employee, or always choose at check-in?
4. Annual leave allowance per type, and does it carry over?

---

## After phase 5: backlog

Ideas worth keeping, not yet planned:

- A read-only client page with a payment status link (no login).
- Employees file expenses from Discord with a photo of the receipt.
- Bank statement import to match payments automatically.
- Legal invoices (TVA, timbre, NIF/NIS/RC) if the company needs them.
- Connect tasks from a project tool to time segments.
- An Arabic or French interface, if the team ever needs one.
