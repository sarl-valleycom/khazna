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

- [x] **Holidays** (owner only): list, add (date and label) and remove. Islamic holidays follow the moon and are announced late, so **do not compute them**; the owner enters them when they are announced. A note on the screen says so.
- [x] **Leave** (owner only): add leave for an employee with type (paid, unpaid, sick), first and last day, and a reason. The owner enters it, so it is approved at once (status `approved`, decided by the owner). Cancel a leave from the list. Employees do not request leave in this phase.
- [x] The type is a label only. It does not change pay: the owner decides any deduction in the month's adjustments.
- [x] Every change is audited, with a reason for a cancellation.
- [x] Hand-computed test: a 5 day week with one holiday and one approved leave day gives the expected minutes on the employee page (for example 3 working days left × 480 = 1 440 minutes).

## 4.2 Monthly attendance summary

Information only. It never changes a salary.

- [x] `MonthlySummary` for an employee, or for the whole team at once, and a month. It uses the same day-by-day logic as `HoursBalance` (both read `WorkCalendar`), so the page and the documents agree:

| Field | Definition |
|---|---|
| Worked minutes | Sum of session time minus unpaid break time, with the paid break allowance applied per day |
| Expected minutes | Work days minus holidays minus approved leave, times the employee's expected minutes per day. Only days between the hire date and the last day count |
| To make up | `max(0, expected − worked)` |
| Extra | `max(0, worked − expected)` |
| Days worked | Days with at least one session |
| Days off | Work days that were a holiday or approved leave |
| Not yet checked | Sessions auto-closed that nobody corrected, shown as a note so a total is never presented as certain |

- [x] For the **current month** the target runs through today only, so a month in progress is not shown as a debt. A future month is empty.
- [x] All closed and open sessions count (there is no approval step). An open session counts up to now.
- [x] An owner page, `/attendance/summary`, with one row per employee for a month, previous and next month buttons, and a link to the sessions list filtered to that person and month.
- [x] The whole team costs the same few queries however many people there are (holidays and leave are loaded once).
- [x] Hand-computed tests: November 2026 has 22 Sunday-to-Thursday days, so 22 × 480 = 10 560 expected minutes; 21 days worked (10 080) leaves 480 to make up. Holiday, leave, hire date, leaving date and paid break cases are tested the same way.

## 4.3 PDF spike (done)

The layout is English and left to right, but client names and notes may be in Arabic script, and many PDF libraries do not shape Arabic letters correctly. The same client statement (an Arabic client name, an Arabic contact and note, a phone number, mixed Arabic and English cells) was rendered with three candidates and checked by eye at high zoom.

| Candidate | Arabic letters | Word order | Notes |
|---|---|---|---|
| **mPDF** | Joined correctly | Correct | Pure PHP, installs with Composer, no browser on the server. Text stays selectable. Limited CSS (tables and blocks, no flex or grid), which is enough for statements and payslips |
| Headless Chrome | Joined correctly | Correct | Most faithful HTML and CSS, but Chrome must be installed and kept up to date on the server, with its sandbox and memory cost |
| dompdf | **Broken**: letters come out separate and the words reversed | Wrong | Not usable for Arabic names |

**Decision: mPDF.** Revisit Chrome only if a later layout needs modern CSS.

Two rules found by the spike, to apply in every template:

- [x] A phone number, an ID or any run of digits next to Arabic text is shown with its groups reversed (`56 34 12 0555`) because of how right-to-left text is ordered. Wrap such values in `<bdi dir="ltr">…</bdi>`. Tested in both mPDF and Chrome: the number then reads `0555 12 34 56`.
- [x] Use mPDF with `autoScriptToLang` and `autoLangToFont` on, so Arabic text picks a font that has the glyphs.

The saved sample is `doc/phases/samples/arabic-client-statement.mpdf.pdf`, made from `arabic-client-statement.html` in the same folder. Re-render it after any change to the PDF setup and compare by eye.

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
- [ ] A client statement, a monthly summary and a payslip reviewed by the owner, including one with an Arabic client name, compared with the saved sample.
- [ ] An accountant (or the owner acting as one) used the ledger export.

## Open questions

1. Should the payslip show the attendance summary at all, or only the money? (Default: show it, as information.)
2. Should finance be able to export attendance? (Default: yes, hours only, never salary.)
