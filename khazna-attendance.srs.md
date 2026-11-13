# Software Requirements Specification: Khazna Attendance

| Item | Value |
|---|---|
| Version | 0.1 (draft) |
| Date | 2026-11-13 |
| Status | For review. Nothing has been built |
| Related document | `khazna-resume.md` (Khazna payment-tracking app) |
| Company | SAASCOM |

---

## 1. Introduction

### 1.1 Purpose

This document specifies the requirements for **Khazna Attendance**: a Discord-based time tracking system. Employees record their working time with slash commands (`/check-in`, `/check-out`, `/tmp-check-out`). The owner uses the recorded hours to pay people correctly, see where the team's time goes, and later measure which projects make money.

### 1.2 Scope

**In scope**

- Slash commands in Discord to start, pause and end a work session.
- Handling of forgotten check-outs, with review and correction by the owner.
- An end-of-day report collected at check-out.
- Reminders, a daily digest and a weekly summary posted in Discord.
- Hours feeding the Khazna salary month-close, as proposals that the owner approves.
- Project tagging of sessions and project profitability (later phase).
- Leave requests with approval (later phase).

**Out of scope**

- Screenshots, keystroke or activity monitoring, voice-channel tracking, geolocation, biometrics.
- Automatic salary deductions without owner approval.
- Leaderboards and gamification.
- Multiple Discord servers (one guild only).
- Legal payroll items (CNAS, IRG). These follow Khazna's own decision on salaries.

### 1.3 Definitions

| Term | Meaning |
|---|---|
| Session | One work period, from check-in to check-out |
| Break (temp-out) | A temporary absence inside a session, opened by `/tmp-check-out` and closed by returning |
| Worked time | Session duration minus break time that is not paid |
| Auto-closed | A session closed by the system because the employee forgot to check out |
| Month-close | The Khazna action that creates one salary due per active employee |
| Guild | The company's Discord server |
| Interaction | A slash command call sent by Discord to the application |

### 1.4 References

- `khazna-resume.md`: stack, data model, roles, month-close.
- Discord Developer Documentation: Interactions, Application Commands, Message Components, Modals.

---

## 2. Overall description

### 2.1 Product perspective

Khazna Attendance is a module of Khazna. It shares the `employees` table and the role system, and it feeds `salary_adjustments`.

```
Employee ──/check-in──> Discord ──HTTPS interaction──> Khazna (Laravel)
                           ^                                 │
                           └──── messages, DMs, digests ─────┤
                                                             ├── attendance tables
Owner ── web app (Vue, English) ─────────────────────────────┤
                                                             └── salary month-close
```

Slash commands are handled through Discord's HTTP interactions endpoint. No always-on bot process is required. Scheduled work (reminders, auto-close, digests) uses the Laravel scheduler and the Discord REST API.

### 2.2 User classes

| User | Needs | Access |
|---|---|---|
| Employee | Record time quickly, fix their own mistakes, see their own hours | Own sessions and reports only |
| Owner | See who is working, review problems, approve hours before pay, read reports | Everything |
| Finance (optional) | Read approved hours for payroll preparation | Hours and totals, not salary amounts unless the Khazna role allows it |
| System | Auto-close, remind, post digests | Scheduler |

### 2.3 Operating environment

- Laravel, Inertia.js with Vue 3, MySQL (same as Khazna).
- Time stored in UTC, displayed in `Africa/Algiers`.
- Discord application with slash commands, registered in one guild.
- Bot messages in English.

### 2.4 Constraints

- Discord requires an acknowledgment of each interaction within 3 seconds. Slower work must use a deferred response.
- Discord signs every interaction with Ed25519. Requests with an invalid signature must be rejected.
- Slash command options and modals have fixed size limits (for example 4000 characters per modal text field).
- Algeria has no daylight-saving change, but all calculations must still use real timestamps, not "hours since midnight".

### 2.5 Assumptions and dependencies

- Every employee has a Discord account and is a member of the guild.
- Each employee is linked once to a Discord user ID by the owner.
- Employees use the commands honestly. The system detects mistakes and gaps but does not try to catch cheating.
- Khazna's `employees` table exists before this module is built.

---

## 3. Functional requirements

Priority: **M** = must, **S** = should, **C** = could. Phase refers to section 9.

### 3.1 Employee identity

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-ID-01 | The owner can link an employee to a Discord user ID in the panel | M | 1 |
| FR-ID-02 | A command from a Discord user who is not linked receives a private message explaining that they are not registered, and nothing is recorded | M | 1 |
| FR-ID-03 | A deactivated employee cannot start sessions | M | 1 |
| FR-ID-04 | Commands only work inside the configured guild | M | 1 |

### 3.2 Session commands

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-SES-01 | `/check-in` creates a session with the current time when the employee has no open session | M | 1 |
| FR-SES-02 | `/check-in` while already working returns a private error stating the start time of the open session | M | 1 |
| FR-SES-03 | `/tmp-check-out [reason]` opens a break inside the open session. A reason is required | M | 1 |
| FR-SES-04 | `/check-in` while away closes the break and sets the state back to working. The confirmation message also carries an "I'm back" button that does the same | M | 1 |
| FR-SES-05 | `/check-out` closes the session. If a break is open it is closed at the same time | M | 1 |
| FR-SES-06 | `/check-out` with no open session returns a private error | M | 1 |
| FR-SES-07 | Each command replies with the recorded time (Algiers) and the running total worked today | S | 1 |
| FR-SES-08 | An employee cannot have more than one open session, even under double clicks or simultaneous requests | M | 1 |
| FR-SES-09 | Check-in and check-out events are posted to a configurable attendance channel. Errors are private to the employee | S | 1 |

State transitions:

| Current state | Command | Result |
|---|---|---|
| Offline | `/check-in` | Working, new session |
| Offline | `/check-out`, `/tmp-check-out` | Error: no open session |
| Working | `/check-in` | Error: already checked in since HH:MM |
| Working | `/tmp-check-out` | Away, break opened |
| Working | `/check-out` | Offline, session closed |
| Away | `/check-in` | Working, break closed |
| Away | `/tmp-check-out` | Error: already away |
| Away | `/check-out` | Offline, break and session closed |

### 3.3 Forgotten check-outs

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-AUTO-01 | A scheduled job closes every session still open at the auto-close time (default 23:59 Algiers) | M | 1 |
| FR-AUTO-02 | An auto-closed session ends at the employee's scheduled end time, or start plus expected daily hours if no schedule exists, and is marked `needs_review` | M | 1 |
| FR-AUTO-03 | The employee receives a DM the next morning with a button to correct the end time | M | 1 |
| FR-AUTO-04 | Auto-closed sessions are excluded from payroll totals until approved by the employee's correction or by the owner | M | 3 |
| FR-AUTO-05 | An employee who has been working more than 10 hours receives a DM asking if they forgot to check out | S | 2 |

### 3.4 End-of-day report

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-REP-01 | `/check-out` opens a modal with two fields: "What did you do today?" (required) and "Any blockers?" (optional) | S | 2 |
| FR-REP-02 | If the modal is dismissed, the session is still closed and the report stays empty | M | 2 |
| FR-REP-03 | Submitted reports are stored with the session and visible to the owner and to the employee | S | 2 |
| FR-REP-04 | The daily digest includes each employee's report | S | 2 |

### 3.5 Reminders and notifications

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-REM-01 | An employee with a schedule who has not checked in 30 minutes after their start time receives a DM (not on weekends, holidays or approved leave) | S | 2 |
| FR-REM-02 | An employee who has been away for longer than a configurable limit (default 90 minutes) receives a DM reminder | C | 2 |
| FR-REM-03 | All reminders can be switched off globally and per employee | S | 2 |

### 3.6 Admin review and correction

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-ADM-01 | The panel lists all sessions with filters by employee, date range and status | M | 1 |
| FR-ADM-02 | The owner can create, edit or delete a session, with a mandatory reason | M | 1 |
| FR-ADM-03 | Every edit is stored in an audit log (who, when, old values, new values, reason) | M | 1 |
| FR-ADM-04 | A "Needs review" view shows auto-closed sessions, sessions over 12 hours and sessions with overlapping times | M | 1 |
| FR-ADM-05 | An employee can request a correction to their own session from Discord. The owner approves or rejects it | S | 2 |
| FR-ADM-06 | The owner can see who is working now (working, away, offline) in the panel | S | 1 |

### 3.7 Reports and digests

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-RPT-01 | A daily digest is posted to a private owner channel: who is in, late, absent, away and forgotten, plus the end-of-day reports | S | 2 |
| FR-RPT-02 | A weekly summary is posted every Sunday: hours per employee against expected hours, lateness count, overtime | S | 2 |
| FR-RPT-03 | `/my-hours [period]` shows an employee their own totals privately | S | 2 |
| FR-RPT-04 | A live "who is online" message in a chosen channel is edited in place when the state changes | C | 2 |
| FR-RPT-05 | Excel/PDF export of monthly hours per employee | C | 3 |

### 3.8 Payroll integration

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-PAY-01 | Each employee has a contract type: fixed monthly, hourly, part-time or freelancer | M | 3 |
| FR-PAY-02 | At month-close, Khazna calculates for each employee the worked time, expected time, shortfall, overtime, late count and absent days from approved sessions | M | 3 |
| FR-PAY-03 | For fixed and part-time employees, the system proposes a deduction or bonus line in draft state. For hourly and freelancer employees, it proposes the amount as worked hours times the hourly rate | M | 3 |
| FR-PAY-04 | Proposals only become `salary_adjustments` after the owner approves them one by one | M | 3 |
| FR-PAY-05 | Month-close is blocked, with a clear message, while the month still has sessions marked `needs_review` | S | 3 |
| FR-PAY-06 | Public holidays, approved leave and the weekend are excluded from expected time | M | 3 |

### 3.9 Project tagging and profitability

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-PRJ-01 | `/check-in` has an optional `project` option with autocomplete from active Khazna contracts | S | 4 |
| FR-PRJ-02 | `/switch project` closes the current segment and opens a new one on another project without ending the session | S | 4 |
| FR-PRJ-03 | Employee cost per hour is calculated as monthly salary divided by expected monthly hours | S | 4 |
| FR-PRJ-04 | A profitability report shows, per contract: hours spent, labor cost, amount contracted or collected, margin | S | 4 |
| FR-PRJ-05 | Time with no project is reported as "unassigned" and not hidden | S | 4 |

### 3.10 Leave and absences

| ID | Requirement | Pri | Phase |
|---|---|---|---|
| FR-LEV-01 | `/leave` opens a form: type (paid, unpaid, sick), dates, reason | S | 5 |
| FR-LEV-02 | The request is sent to the owner with approve and reject buttons. The employee is notified of the decision | S | 5 |
| FR-LEV-03 | Approved leave removes those days from expected time and suppresses reminders | S | 5 |
| FR-LEV-04 | A leave balance per employee per year (optional) | C | 5 |

---

## 4. Discord command reference

| Command | Options | Who | Phase |
|---|---|---|---|
| `/check-in` | `project` (optional, phase 4) | Employee | 1 |
| `/check-out` | none (opens the report modal from phase 2) | Employee | 1 |
| `/tmp-check-out` | `reason` (required) | Employee | 1 |
| `/status` | none. Shows own state and today's total | Employee | 1 |
| `/my-hours` | `period` (today, week, month) | Employee | 2 |
| `/switch` | `project` | Employee | 4 |
| `/leave` | opens a form | Employee | 5 |

Replies containing personal data or errors are ephemeral (visible only to the sender).

---

## 5. Data requirements

New tables are added to the Khazna database. All use soft deletes. Timestamps are stored in UTC.

| Entity | Main fields |
|---|---|
| `employees` (existing, extended) | `discord_user_id` (unique, nullable), `contract_type`, `hourly_rate`, `expected_minutes_per_day`, `work_days`, `schedule_start`, `schedule_end`, `late_grace_minutes`, `reminders_enabled` |
| `attendance_sessions` | `employee_id`, `started_at`, `ended_at`, `status` (open, closed, auto_closed), `review_status` (ok, needs_review, approved), `report`, `blockers`, `source` (discord, admin), `closed_by` |
| `attendance_breaks` | `session_id`, `started_at`, `ended_at`, `reason`, `paid` (value copied at creation) |
| `attendance_segments` (phase 4) | `session_id`, `contract_id` (nullable), `started_at`, `ended_at` |
| `attendance_edits` | `session_id`, `edited_by`, `old_values`, `new_values`, `reason`, `created_at` |
| `correction_requests` | `session_id`, `employee_id`, `proposed_ended_at`, `note`, `status`, `decided_by` |
| `holidays` | `date`, `label` |
| `leave_requests` (phase 5) | `employee_id`, `type`, `from`, `to`, `reason`, `status`, `decided_by` |
| `attendance_settings` | auto-close time, digest time, channel IDs, reminder limits, paid break allowance |

---

## 6. Business rules

| ID | Rule |
|---|---|
| BR-01 | An employee has at most one open session at any time |
| BR-02 | Worked time = session duration minus break time, except for the paid break allowance (default 0 minutes per day) |
| BR-03 | An employee is late when check-in is after `schedule_start` plus `late_grace_minutes` (default 15). Employees without a schedule are never marked late |
| BR-04 | A session crossing midnight belongs to the day it started |
| BR-05 | Hours are never turned into pay automatically. Month-close creates proposals, and the owner approves each one |
| BR-06 | Auto-closed sessions never count toward pay until reviewed |
| BR-07 | Every change made by an admin needs a reason and is logged |
| BR-08 | Employees can see their own data only. Only the owner sees everyone's |
| BR-09 | Expected time excludes weekends (default Friday and Saturday), holidays and approved leave |

---

## 7. Non-functional requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Performance | Every interaction is acknowledged within 3 seconds. Heavy work uses deferred replies |
| NFR-02 | Reliability | Simultaneous or repeated commands never create two open sessions. Enforced in the database (transaction with a row lock on the employee), not only in code |
| NFR-03 | Security | Reject any request without a valid Ed25519 signature. Store the bot token and public key in environment variables only |
| NFR-04 | Security | Attendance and salary data is protected by policies on every action and every Livewire method |
| NFR-05 | Privacy | Employees are told which data is recorded and who sees it. No monitoring beyond the commands they send |
| NFR-06 | Auditability | Edits, approvals and payroll proposals are traceable to a user and a time |
| NFR-07 | Localization | English interface and English bot messages, Algiers time everywhere users see time |
| NFR-08 | Testability | Hour calculations (midnight crossing, breaks, auto-close, month totals) have tests with hand-computed expected values |
| NFR-09 | Availability | If Khazna is down, Discord shows an error to the employee. They must be told to retry. The system must not silently lose a check-in |
| NFR-10 | Maintainability | Same conventions, helpers and structure as Khazna and the CRM |
| NFR-11 | Retention | Sessions and reports are kept for at least the period required for payroll disputes (default 3 years) |

---

## 8. Key scenarios (acceptance)

| # | Given | When | Then |
|---|---|---|---|
| 1 | Employee offline | `/check-in` at 09:02 | Session opened, reply shows 09:02 Algiers, event posted to the attendance channel |
| 2 | Employee working | `/check-in` again | Private error with the original start time. No second session |
| 3 | Employee working | `/tmp-check-out reason:prayer`, then `/check-in` 20 minutes later | One break of 20 minutes. State is working |
| 4 | Employee away | `/check-out` | Break and session closed at the same moment. Report modal appears |
| 5 | Session open at 23:59 | Auto-close job runs | Session closed at scheduled end, marked `needs_review`, employee gets a DM next morning |
| 6 | Auto-closed session in the month | Owner starts month-close | Month-close is blocked until the session is approved or corrected |
| 7 | Two `/check-in` requests arrive within the same second | Both processed | Exactly one session exists |
| 8 | Session 22:00 to 02:00 next day | Monthly totals computed | Four hours counted, assigned to the day it started |
| 9 | Unlinked Discord user | Any command | Private message, nothing recorded |
| 10 | Owner edits a session end time | Saves with reason | Change applied, audit row created |

---

## 9. Phases

| Phase | Content | Done when |
|---|---|---|
| 1. Core | Employee linking, 3 commands plus `/status`, state rules, auto-close, admin list, edit with audit, review view | A team can use it daily and the owner can trust the numbers |
| 2. Visibility | End-of-day report, reminders, daily digest, weekly summary, `/my-hours`, correction requests | The owner no longer needs a stand-up meeting |
| 3. Payroll | Contract types, holidays, expected time, month-close proposals, approval flow, exports | A month's salaries are prepared from hours with no spreadsheet |
| 4. Projects | Project tagging, `/switch`, cost per hour, profitability report | The owner can see margin per contract |
| 5. Leave | `/leave`, approvals, balances | Absences are handled inside the system |

---

## 10. Open decisions

| # | Question | Default assumed if not answered |
|---|---|---|
| 1 | Team size, and which contract types exist (fixed monthly, hourly, part-time, freelancer)? | Fixed monthly and hourly |
| 2 | Is time spent on `/tmp-check-out` paid? | Not paid, no allowance |
| 3 | Do all employees have a fixed schedule, or are some flexible? | Fixed schedule optional per employee. No schedule means no lateness |
| 4 | Should auto-closed sessions count for pay until reviewed? | No, they are excluded until approved |
| 5 | Language of bot messages | English (decided) |
| 6 | Where should check-in and check-out events be visible? | One attendance channel visible to all employees |
| 7 | Can employees see each other's hours? | No, only their own |
| 8 | Do employees work remotely or in an office? | Remote or mixed. No location check |
| 9 | Auto-close time and the late grace period | 23:59 and 15 minutes |
| 10 | What happens with unpaid lateness: deduction, warning only, or nothing? | Warning only in the digest. Deduction only if the owner decides during month-close |
