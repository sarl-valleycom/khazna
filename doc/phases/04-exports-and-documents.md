# Phase 4: Exports and documents

| | |
|---|---|
| SRS phase | 4, reduced to exports and documents |
| Effort | About 1 week |
| Depends on | Phases 2 and 3 |
| Outcome | The owner can hand a client, an employee or an accountant a clean file: Excel for any list, PDF for statements and payslips |
| Requirements | FR-SAL-08, FR-EXP-01..04, FR-RPT-05, and FR-PAY-06 from the attendance annex (the holiday and leave screens) |

**What this phase no longer contains.** Salary does not depend on fixed hours, so the plan to prepare payroll from attendance (expected time, monthly summary as a pay input, payroll proposals, month-close blocked by unreviewed sessions) is dropped. The owner keeps entering bonuses, deductions and advances by hand, as in phase 2. The automatic WhatsApp reminders (n8n), the CRM sync and the mandatory two-factor are also not planned now. They are listed under "Later" in `README.md`, so nothing is lost.

Build in this order: 4.1, then 4.2, then the PDF spike (4.3) before any PDF work.

---

## 4.1 Holidays and leave screens

The hours-to-make-up numbers on the employee page already subtract holidays and approved leave (`WorkCalendar`), but today the owner has no way to enter either. Until then every holiday counts as missed time.

- [ ] **Holidays** (owner only): list, add (date and label) and remove. Islamic holidays follow the moon and are announced late, so **do not compute them**; the owner enters them when they are announced. A note on the screen says so.
- [ ] **Leave** (owner only): add leave for an employee with type (paid, unpaid, sick), first and last day, and a reason. The owner enters it, so it is approved at once (status `approved`, decided by the owner). Cancel a leave from the list. Employees do not request leave in this phase.
- [ ] The type is a label only. It does not change pay: the owner decides any deduction in the month's adjustments.
- [ ] Every change is audited, with a reason for a cancellation.
- [ ] Hand-computed test: a 5 day week with one holiday and one approved leave day gives the expected minutes on the employee page (for example 3 working days left × 480 = 1 440 minutes).

## 4.2 Monthly attendance summary

Information only. It never changes a salary.

- [ ] `MonthlySummary` for an employee and a month, built from the same day-by-day logic as `HoursBalance` (extend it, do not copy it), so the page and the documents agree:

| Field | Definition |
|---|---|
| Worked minutes | Sum of session time minus unpaid break time, with the paid break allowance applied per day |
| Expected minutes | Work days minus holidays minus approved leave, times the employee's expected minutes per day |
| To make up | `max(0, expected − worked)` |
| Extra | `max(0, worked − expected)` |
| Days worked | Days with at least one session |
| Days off | Holidays and approved leave days that fell on a work day |
| Not yet checked | Sessions auto-closed that the employee or owner has not corrected, shown as a note so a total is never presented as certain |

- [ ] All closed and open sessions count (there is no approval step). An open session counts up to now.
- [ ] A screen per employee and month on the owner pages, with a link to every session behind a number.
- [ ] Hand-computed test: November with 4 weekends, one holiday and 22 expected days gives 22 × 480 = 10 560 expected minutes; 10 080 worked leaves 480 to make up.

## 4.3 PDF spike (half a day)

The layout is English and left to right, but client names and notes may be in Arabic script, and many PDF libraries do not shape Arabic letters correctly.

- [ ] Test a client statement that contains an Arabic client name with each candidate. A headless-Chrome based renderer is the most reliable. A lighter library is acceptable only if it renders the Arabic name correctly.
- [ ] Choose from what the spike shows and record the choice and the reason here. Keep the test PDF as a saved sample.

## 4.4 Excel exports

- [ ] Install `maatwebsite/excel`.
- [ ] An export uses the **same query object** as the screen, so a filtered list exports exactly what is shown (FR-EXP-01). The query objects already exist: `ClientIndexQuery`, `ContractIndexQuery`, `DueTriageQuery`, `SessionIndexQuery`, `CashReportQuery`.
- [ ] Chunk large exports.
- [ ] Exports respect the same permissions as the screens (FR-EXP-04). A finance user cannot export salary dues, and payroll lines follow the same masking rule as the screens.
- [ ] Export buttons on: clients, contracts, dues, payments, the sessions list, and the cash reports.

## 4.5 PDF documents

- [ ] **Client statement:** dues and payments for a period (FR-EXP-02).
- [ ] **Monthly summary:** money in, money out, outstanding, and the salary total. The salary line is owner only (FR-EXP-03).
- [ ] **Payslip** per employee per month: salary, bonuses, deductions, advances, net (from `PayrollCalculator`, so it can never disagree with the close), plus the 4.2 attendance summary as information, with no deduction taken from it (FR-SAL-08). Generated on demand from stored data, with the same permissions as the salary pages.

## 4.6 Attendance and ledger exports

- [ ] **Attendance monthly export** per employee, Excel and PDF: sessions, worked, expected, to make up (FR-RPT-05).
- [ ] **Account statement** for a date range, Excel and PDF, and the cash reports from phase 2 (money in and out by month and category, office report) as Excel. These are what an accountant will ask for. Payroll lines follow the same masking rule as the screens.

---

## Test plan

- Every hand-computed example above.
- Leave and holiday screens: only the owner can open them. A day marked as a holiday or leave changes the expected minutes on the employee page.
- An export contains exactly the rows of the filtered screen. A finance user cannot export salary dues or see the payroll line.
- The payslip net equals the month-close net for the same employee and month.
- The PDF renders an Arabic client name correctly (visual check kept as a saved sample).

## Exit checklist

- [ ] Holidays and leave entered for the current year, and the employee page matches a manual count.
- [ ] A client statement, a monthly summary and a payslip reviewed by the owner, including one with an Arabic client name.
- [ ] An accountant (or the owner acting as one) used the ledger export.

## Open questions

1. PDF library: decided by the spike in 4.3.
2. Should the payslip show the attendance summary at all, or only the money? (Default: show it, as information.)
3. Should finance be able to export attendance? (Default: yes, hours only, never salary.)
