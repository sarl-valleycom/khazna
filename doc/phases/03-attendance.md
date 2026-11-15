# Phase 3: Attendance

| | |
|---|---|
| SRS phase | 3 (annex phases 1 and 2) |
| Effort | 2 to 3 weeks |
| Depends on | Phase 2 (employees, month-close) and a public HTTPS URL |
| Outcome | The team uses `/check-in` daily and you trust the numbers |
| Requirements | FR-ATT-01..04. From the annex: FR-ID-01..04, FR-SES-01..09, FR-AUTO-01..03 and 05, FR-REP-01..04, FR-REM-01..03, FR-ADM-01..03 and 05..06, FR-RPT-03 and 04 |

**How it works, in short.** An employee sends `/check-in` to the bot in a DM (or in the server). The session is saved, and each day the app counts how much time they worked. That is all the employee has to do.

Salary does not depend on fixed hours. Payroll use of the hours (FR-AUTO-04, FR-ATT-05) comes in phase 4. Until then, hours are visible but do not touch salaries.

The Discord digests (FR-RPT-01, 02) and the owner "needs review" page are not part of this phase. The owner finds auto-closed sessions with the status filter on the sessions list.

---

## 3.1 Discord application setup

Do this per environment (local, staging, production each get their own application).

- [ ] Create the application in the Discord developer portal. Note the **Application ID**, **Public Key** and **Bot Token**.
- [ ] Invite the bot to the company server with the `applications.commands` and `bot` scopes. Permissions: view channels, send messages. No privileged intents are needed because the app uses HTTP interactions and no gateway connection.
- [ ] Note the **Guild ID** and create the channels: the attendance feed and the reports channel. Both offices share one Discord server and everything lives in one place (decision 4), so give both offices the same attendance channel ID. Every channel is visible to all employees (decision 2).
- [ ] Store the values in `.env` and `config/discord.php`. Nothing about Discord is hardcoded.
- [ ] Set the **Interactions Endpoint URL** to `https://<domain>/discord/interactions`. Discord verifies it by sending a signed ping and an invalid-signature probe, so the endpoint must already work.
- [ ] For local work, expose the dev server with a tunnel such as `cloudflared`.

## 3.2 Interactions endpoint

- [ ] `POST /discord/interactions`, outside the session and CSRF middleware, behind a **signature middleware**:
  - Read headers `X-Signature-Ed25519` and `X-Signature-Timestamp`.
  - Verify the signature of `timestamp + raw body` against the public key with PHP's built-in libsodium (`sodium_crypto_sign_verify_detached`).
  - Reject with 401 when invalid. Never parse the body before verifying.
- [ ] Answer the ping interaction (type 1) with type 1.
- [ ] `InteractionRouter`: maps interaction type and command name (or button `custom_id` prefix) to a handler class. Handlers return an `InteractionResponse` object with helpers for a normal message, an ephemeral message, a deferred response, and a modal.
- [ ] Reject interactions from any server other than the configured one (FR-ID-04). A command, button or modal sent in a **DM with the bot** has no `guild_id` and is routed, because the handlers only act for a linked employee on their own sessions. An unlinked user gets a private explanation.
- [ ] **Time budget:** each handler must answer within 3 seconds. Handlers only touch the database. Posting to channels and sending DMs happens in queued jobs. For anything slower, send a deferred response and finish by editing the original message.
- [ ] Handlers call the **same Actions** as the web app (`StartSession`, `EndSession`…). They contain no business rules.
- [ ] All replies come from `lang/en/discord.php`. Replies that carry personal data or errors are ephemeral.
- [ ] `CommandRegistry` is the single definition of the commands. Every command is registered **globally** with `contexts` for a server and for a DM with the bot, because Discord only shows a command in a DM when it is global. The artisan command `discord:register-commands` pushes it, then clears the old guild copies so a command does not appear twice. Global commands can take up to an hour to show after a change.

## 3.3 Linking employees

- [ ] Migration: `employees` gains `discord_user_id` (**string**, nullable, unique), `contract_type`, `hourly_rate`, `expected_minutes_per_day`, `work_days`, `schedule_start`, `schedule_end`, `late_grace_minutes`, `reminders_enabled`.
- [ ] Discord IDs are 64-bit integers. Always handle them as strings, in PHP, in the database and in TypeScript. A JavaScript number would silently change the value.
- [ ] Employee form: a field to paste the Discord user ID, with a note on how to copy it. Optionally verify it by sending the employee a test DM from a button.
- [ ] An unlinked or deactivated user gets a private explanation and nothing is recorded (FR-ID-02, 03).

## 3.4 Session engine

Tables: `attendance_sessions`, `attendance_breaks`, `attendance_settings` (annex section 5).

- [ ] **Database-level guarantee** of one open session per employee. MySQL has no partial unique index, so use a generated column with a unique index:
  - `open_employee_id` = `IF(ended_at IS NULL AND deleted_at IS NULL, employee_id, NULL)` stored, unique. Unique indexes allow many NULLs, so closed sessions never collide.
  - Do the same for breaks (`open_session_id`).
  - Code catches the unique violation and answers "already checked in since HH:MM" (FR-SES-02, 08).
- [ ] Actions: `StartSession`, `StartBreak`, `EndBreak`, `EndSession`. Each runs in a transaction and locks the employee row first. The state table in the annex section 3.2 is the contract.
- [ ] `/check-in` while away ends the break. The confirmation message carries an "I'm back" button that does the same (FR-SES-04).
- [ ] `/tmp-check-out` requires a reason option.
- [ ] Replies include the time in Algiers and today's running total (FR-SES-07). Events are posted to the attendance channel through a queued job (FR-SES-09).
- [ ] Worked-time calculation (`WorkedTime`): session duration minus breaks, using real timestamps, assigned to the day the session started. The paid break allowance is a per-day rule: it is subtracted from each Algiers day's break time once, never once for a whole week or month. Unit tests for sessions crossing midnight, several breaks, an open break at close, and the allowance over several days.

## 3.5 Auto-close and corrections

- [ ] `attendance:auto-close`, scheduled at the configured time (default 23:59 Algiers).
- [ ] Closing time: the employee's scheduled end if it exists, otherwise start plus expected daily minutes. If the session started **after** that time, close at the current time. Always mark `needs_review` and `status = auto_closed` (FR-AUTO-01, 02).
- [ ] The next morning, DM the employee with a button to give the real end time. A short modal collects it. The change is saved as a **correction request** for the owner to approve, unless self-correction is on, which it is by default (decision 1; the owner can turn it off on the settings page). A correction is applied without the owner only when all of these hold: self-correction is enabled, the session is auto-closed and still waiting for review, the request is inside the self-correction window, and the proposed session is at most 12 hours long. A self-approval marks the session reviewed, so it cannot be repeated to push the end time forward. Anything else stays pending for the owner.
- [ ] The correction DM goes to every auto-closed session that has not had one yet (`correction_dm_sent_at` is empty), including sessions closed after midnight, not only those that ended the day before.
- [ ] "Over 10 hours" reminder to the employee (FR-AUTO-05).

## 3.6 Admin pages (Vue)

- [ ] **Sessions** list with filters (employee, office, date range, status), using `DataTable` and a `SessionIndexQuery` (FR-ADM-01). The status filter finds auto-closed sessions, and the owner can approve (mark as checked), edit or delete them.
- [ ] **Edit session** dialog: every change needs a reason and writes an audit row (FR-ADM-02, 03). Deleting a session, or changing its start or end, rejects its pending correction requests so a stale proposal cannot overwrite the owner's edit.
- [ ] **Who is working now** page (working, away, offline), refreshed every 30 seconds with Inertia's polling helper and a partial reload of one prop (FR-ADM-06). Today's hours for all employees load in two queries.
- [ ] Correction requests inbox with approve and reject (FR-ADM-05).

## 3.7 End-of-day report

Discord only allows a modal as the **immediate** answer to an interaction, and a dismissed modal must not leave the session open. So:

1. `/check-out` closes the session at once and replies with a short summary and a **"Write today's report"** button.
2. The button opens a modal with two fields: what you did (required), blockers (optional).
3. Submitting saves both on the closed session (FR-REP-01..03) and posts the report, with the employee's mention and the date, to the reports channel through a queued job. With no reports channel set, the report is only saved. A report is cut to fit one Discord message.

This is a small change from the annex wording ("`/check-out` opens a modal"). The result for the employee is the same, and the session is always closed (FR-REP-02).

## 3.8 Reminders and presence

- [ ] `DiscordClient` service: sends channel messages and DMs through the REST API. Handles rate limits (honors the retry-after delay), retries 5xx errors and network failures (timeouts, dropped connections) with backoff, and logs failures. After the last attempt it reports a failed send and never throws into a job, so the DM fallback always runs.
- [ ] **DMs can fail** when the employee has DMs from server members turned off. Fall back to a mention in the attendance channel and log it.
- [ ] `attendance:remind`, every 5 minutes: not checked in 30 minutes after the scheduled start (not on weekends, holidays or leave), away longer than the limit. Respect `reminders_enabled` globally and per employee (FR-REM-01..03).
- [ ] An employee's check-in events go to the attendance feed of their own office.
- [ ] Optional live "who is online" message, edited in place on every state change (FR-RPT-04).
- [ ] Use embeds with English text and real mentions (`<@id>`).

## 3.9 Employee self-service commands

- [ ] `/status`: own state and today's total (ephemeral).
- [ ] `/my-hours period:<today|week|month>`: own totals (ephemeral) (FR-RPT-03).

---

## Routes and pages

| Route | Page | Roles |
|---|---|---|
| `POST /discord/interactions` | none | Discord only (signature) |
| `/attendance/sessions` | `attendance/sessions` | owner |
| `/attendance/presence` | `attendance/presence` | owner |
| `/attendance/corrections` | `attendance/corrections` | owner |
| `/attendance/settings` | `attendance/settings` | owner |

## Test plan

- A `SignedInteraction` test helper: generates an Ed25519 keypair, signs the payload, and posts it. Used by every handler test.
- Annex scenarios 1 to 5 and 7 to 10, with `Carbon::setTestNow` for time.
- Wrong or missing signature returns 401. A command from the wrong server is rejected. A command, button or modal sent in a DM is routed, and a linked employee's DM `/check-in` saves a session.
- `discord:register-commands` registers global commands with DM context and clears the guild copies.
- A network failure to Discord is retried and then reported as a failed send, and the DM falls back to the attendance channel.
- Self-correction: a manually closed session, a second correction, and a session over 12 hours all wait for the owner.
- Deleting or editing a session rejects its pending corrections.
- The paid break allowance is applied per day over a week in `/my-hours`.
- Double `/check-in`: the second one fails cleanly. A test that inserts two open rows must hit the unique index.
- Session crossing midnight (22:00 to 02:00) counts four hours on the start day.
- Auto-close for a session started after the scheduled end.
- A deactivated employee cannot start a session.
- Discord ID kept as a string end to end (a value above 2^53 round-trips unchanged).

## Exit checklist

- [ ] Real Discord server on staging, with the whole team's accounts linked.
- [ ] One week of pilot with 2 or 3 employees. Every forgotten check-out was caught and reviewed.
- [ ] Reminders arrive at the right Algiers times.
- [ ] Owner pages reviewed on a phone.
- [ ] Staff were told what is recorded and who sees it (NFR-13).

## Risks

| Risk | Mitigation |
|---|---|
| Employees forget to check in or out | Reminders, auto-close, and a simple correction path |
| The 3-second limit is missed | Handlers touch only the database. Everything else is queued or deferred |
| A Discord outage blocks work records | The web app lets the owner enter or fix sessions manually |
| Staff see the system as surveillance | Record only the commands they send, explain it first, no extra monitoring |
| DMs are closed | Channel fallback and a log of undelivered messages |

## Decisions

These were open questions. They are now settled.

1. **Employees fix their own forgotten check-out.** Self-correction is on by default, with a 24 hour window. The owner can turn it off, or change the window, on `/attendance/settings`. The limits in section 3.5 still apply: only an auto-closed session still waiting for review, at most 12 hours long, and only once. An existing database keeps its saved setting, so switch it on in settings there.
2. **Every channel is visible to all employees.** There is no owner-only channel in this phase, which is also why the owner digest was dropped.
3. **Defaults accepted:** auto-close at 23:59 and a 15 minute late grace period.
4. **Everything in one place.** Both offices share one Discord server and one attendance channel. The per-office channel setting stays, so an office can get its own feed later. Working hours and work days are set per employee.
