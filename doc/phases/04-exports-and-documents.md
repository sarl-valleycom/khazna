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

- [x] `maatwebsite/excel` 4.x installed (it supports Laravel 13).
- [x] An export walks the **same query builder** as its screen, so a filtered list exports exactly what is shown, all pages and not just the one on screen (FR-EXP-01). The query objects now expose `query()` (`rowsQuery()` for dues) and the screen paginates that same builder. Each query ends with a unique tie-break (the id), because a chunked export that pages with `LIMIT/OFFSET` would otherwise skip or repeat rows that tie on the sort column. Chunked by 1 000 rows.
- [x] Exports respect the same visibility as the screens (FR-EXP-04): an account manager exports only their own clients, and a finance user cannot export salary dues, because the dues query already hides them. The sessions export is owner only, like the sessions list.
- [x] Export buttons ("Export to Excel", carrying the screen's current filters) on **clients**, **contracts**, **dues** (the triage tab you are on; "needs attention" lists contracts, so it has none) and **sessions**.
- [x] A spreadsheet is safe to open: text is stored as text, so a client named `=HYPERLINK(...)` is never run as a formula, and a phone number keeps its leading zero. Dates are real dates (`yyyy-mm-dd`), money is a number in DA with two decimals (it only displays; Khazna's own totals stay in centimes), the header row is bold and frozen, and columns are sized to their content.
- [ ] **Payments** have no list screen of their own, so there is nothing to mirror. They are exported inside the client statement (4.5) and the account statement (4.6). The **cash reports** are exported in 4.6.

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
- An export contains exactly the rows of the filtered screen (all pages), never skips or repeats a row, and keeps phone numbers and formula-like text intact. A finance user cannot export salary dues or see the payroll line.
- The payslip net equals the month-close net for the same employee and month.
- The PDF renders an Arabic client name correctly (visual check kept as a saved sample).

## Exit checklist

- [ ] Holidays and leave entered for the current year, and the employee page matches a manual count.
- [ ] A client statement, a monthly summary and a payslip reviewed by the owner, including one with an Arabic client name, compared with the saved sample.
- [ ] An accountant (or the owner acting as one) used the ledger export.

## Open questions

1. Should the payslip show the attendance summary at all, or only the money? (Default: show it, as information.)
2. Should finance be able to export attendance? (Default: yes, hours only, never salary.)
