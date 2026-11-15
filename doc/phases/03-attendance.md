# Phase 3: Attendance

| | |
|---|---|
| SRS phase | 3 (annex phases 1 and 2), changed: a web page replaces the Discord bot |
| Depends on | Phase 2 (employees, month-close) |
| Outcome | The team checks in and out on one page, and you trust the numbers |
| Requirements | FR-ATT-01..04. From the annex: FR-SES-01..09, FR-AUTO-01..03, FR-REP-01..03, FR-ADM-01..03 and 05..06 (the annex wording says Discord; read it as the web page) |

**How it works.** An employee opens `/work` on a phone or computer and types a personal 6 digit code. There is no account and no password. The page shows one big button to check in and out, a live timer since check-in, the hours still to make up, and the recent sessions. Each day the app counts how much time the employee worked.

**Salary does not depend on fixed hours.** The target hours are information for the employee and the owner. Payroll use of the hours (FR-AUTO-04, FR-ATT-05) comes in phase 4. Until then, hours are visible but do not touch salaries.

**There is no Discord.** The bot, its commands, DMs, reminders and digests were dropped. Nothing is pushed to the employee: the page shows what needs attention (a forgotten check-out, a missing report) when they open it.

---

## 3.1 The employee code

- [x] The owner makes a code on the employee page (`Make a code`). It is 6 digits, shown **once**, and cannot be read back. `Make a new code` replaces it and the old one stops working. `Remove` deletes it.
- [x] Only a keyed hash (HMAC with the app key) is stored, never the code. The hash is unique, so a code identifies one employee. Changing a code leaves an audit row (who and when) without logging the hash.
- [x] Only an active employee can use a code. Deactivating an employee closes the door.
- [x] Guessing is slowed down: 5 tries a minute and 30 an hour per address. A six digit code is weaker than a password, so the page also never says which employee a code belongs to.
- [x] After a valid code the page keeps the employee in the session for 14 hours (a working day). `Leave` ends it at once, for a shared computer.
- [ ] Tell staff the address (`https://<domain>/work`) and give each person their code in private.

## 3.2 Check-in and check-out

- [x] One page, `/work`, outside the owner login. It reuses the same Actions as the owner pages: `StartSession`, `StartBreak`, `EndBreak`, `EndSession`.
- [x] **Database guarantee** of one open session per employee: a stored generated column with a unique index (`open_employee_id`), and the same for breaks (`open_session_id`). A double tap answers "already checked in since HH:MM".
- [x] Check in, take a break (a reason is required), come back, check out. Each action answers with a message and the time in Algiers.
- [x] Worked time is computed from real timestamps, never stored as a total. A session belongs to the Algiers day it started on, even when it crosses midnight. The paid break allowance is a per-day rule: it is subtracted from each day's break time once.
- [x] A deactivated employee cannot start a session.

## 3.3 What the employee sees

- [x] **Live timer** since check-in. It counts from the server clock, so a phone with a wrong clock is still right. Worked today and the time still to do today count live while working, and stop on a break.
- [x] **Hours to make up.** The target comes from the employee's own expected minutes per day and work days, minus holidays and approved leave. The page shows worked against target for today, this week (from Sunday) and this month. "To make up" is what is missing from the **days before today**, so a morning in progress is never counted as a debt. Extra time shows as "ahead", never as a negative.
- [x] **Recent sessions**: the last ten, with start, end, worked time, the report and a note when a session was closed automatically.
- [x] **End-of-day report**: after check-out, a finished session without a report asks for one (what you did, blockers). Saved on the session.
- [x] **Forgotten check-out**: an auto-closed session shows a form for the real end time (see 3.4).

## 3.4 Auto-close and corrections

- [x] `attendance:auto-close`, scheduled at the configured time (default 23:59 Algiers). It closes every session still open, at the employee's scheduled end if there is one, otherwise at the current time. The session is marked `auto_closed` and needs review (FR-AUTO-01, 02).
- [x] The employee corrects it on `/work` with the real end time (`HH:MM`, Algiers) and an optional note. The change is applied at once only when **all** of these hold: self-correction is on (it is by default), the session is auto-closed and still waiting for review, it is inside the self-correction window (24 hours by default), and the session would be at most 12 hours long. A self-approval marks the session reviewed, so it cannot be repeated to push the end forward. Anything else becomes a request for the owner.
- [x] Deleting a session, or changing its start or end, rejects its pending correction requests, so a stale proposal cannot overwrite the owner's edit.

## 3.5 Owner pages

- [x] **Sessions** list with filters (employee, office, date range, status). The status filter finds auto-closed sessions. The owner can add, edit (a reason is required and every change writes an audit row), delete and approve (mark as checked) a session (FR-ADM-01..03).
- [x] **Who is working now**: working, away or offline, refreshed every 30 seconds. Today's hours for everyone load in two queries (FR-ADM-06).
- [x] **Corrections** inbox with approve and reject (FR-ADM-05).
- [x] **Settings**: auto-close time, paid break allowance, self-correction on or off and its window. Every save is audited.
- [x] **Employee page**: the attendance code, contract type, schedule, expected minutes per day and work days.

## Routes and pages

| Route | Who |
|---|---|
| `/work` (code, check-in and out, hours, report, correction) | Employees, by personal code |
| `/attendance/sessions`, `/presence`, `/corrections`, `/settings` | Owner |
| `POST /employees/{id}/attendance-code` and `DELETE` | Owner |

## Test plan

- [x] Code: a valid code opens the page, a wrong or deactivated one does not, spaces and dashes are ignored, only a hash is stored, guessing is rate limited, the sign-in ends after a working day, and `Leave` forgets it.
- [x] Check in, break, back and check out through the page. A second check-in is refused. A break needs a reason. Actions without a sign-in go back to the code page.
- [x] Hours: today, week and month against the target, the carry-over from earlier days, extra time never negative, the paid break allowance applied per day.
- [x] Reports and corrections: an employee cannot touch someone else's session, a malformed time is refused, and the self-correction limits (manual session, repeat, over 12 hours, outside the window).
- [x] Owner pages: sessions, presence (a fixed number of queries), corrections, settings, and the audit rows.

## Exit checklist

- [ ] Run `php artisan migrate`. It drops the old Discord columns and the reminders table.
- [ ] A week of pilot with 2 or 3 employees. Every forgotten check-out was caught and corrected.
- [ ] `php artisan schedule:work` (or cron) runs, so auto-close happens at night.
- [ ] Owner and employee pages reviewed on a phone.
- [ ] Staff were told what is recorded and who sees it (NFR-13).

## Risks

| Risk | Mitigation |
|---|---|
| Someone checks in for a colleague with their code | Codes are private and the owner sees every session. A code can be replaced at once |
| A six digit code is guessed | Rate limit per address, no hint about who owns a code, and the owner can replace a code |
| Employees forget to check in or out | Auto-close, a clear banner on the page, and a simple correction path |
| A shared computer stays signed in | The sign-in lasts 14 hours, and `Leave` ends it |
| The owner needs to fix a day nobody recorded | The sessions page adds or edits a session with a reason |

## Decisions

1. **Employees fix their own forgotten check-out.** Self-correction is on by default with a 24 hour window. The owner can turn it off in settings. An existing database keeps its saved setting.
2. **No chat channels.** Nothing is posted anywhere. The owner reads sessions, reports and presence on the owner pages.
3. **Defaults:** auto-close at 23:59. The command runs every minute and closes whatever was open at the most recent auto-close time, so a late scheduler catches up.
4. **One place.** Both offices use the same page and the same owner pages. Working hours and work days are set per employee.
