<?php

namespace App\Http\Controllers\Work;

use App\Actions\Attendance\EndBreak;
use App\Actions\Attendance\EndSession;
use App\Actions\Attendance\SaveSessionReport;
use App\Actions\Attendance\StartBreak;
use App\Actions\Attendance\StartSession;
use App\Actions\Attendance\SubmitCorrection;
use App\Attendance\AttendanceCode;
use App\Attendance\AttendanceException;
use App\Attendance\HoursBalance;
use App\Attendance\WorkedTime;
use App\Data\WorkDashboardData;
use App\Data\WorkHoursPeriodData;
use App\Data\WorkSessionData;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee page: no login, just a personal code. After the code is
 * accepted the employee id is kept in the session for a working day, so
 * check-in and check-out are one tap. Every action goes through the same
 * Actions the owner pages use.
 */
class WorkController extends Controller
{
    private const SESSION_KEY = 'work.employee_id';

    private const EXPIRES_KEY = 'work.expires_at';

    private const FINGERPRINT_KEY = 'work.code_fingerprint';

    /** Wrong codes allowed per address, then a pause. Right codes never count. */
    private const FAILS_PER_MINUTE = 10;

    private const FAILS_PER_HOUR = 40;

    /** Wrong codes across every address, so a spread-out guesser still cannot get through. */
    private const FAILS_PER_HOUR_ALL = 400;

    /** A working day, so a phone left logged in does not stay open for ever. */
    private const SESSION_HOURS = 14;

    public function show(Request $request, HoursBalance $balance): Response
    {
        $employee = $this->employee($request);

        if ($employee === null) {
            return Inertia::render('work/Enter');
        }

        return Inertia::render('work/Dashboard', [
            'dashboard' => $this->dashboard($employee, $balance),
        ]);
    }

    public function enter(Request $request, AttendanceCode $codes): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $keys = [
            'work-fail-minute:'.$request->ip() => [self::FAILS_PER_MINUTE, 60],
            'work-fail-hour:'.$request->ip() => [self::FAILS_PER_HOUR, 3600],
            'work-fail-all' => [self::FAILS_PER_HOUR_ALL, 3600],
        ];

        // Only wrong codes count, so a whole office behind one address can sign in together.
        foreach ($keys as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return back(303)->withErrors(['code' => __('attendance.errors.too_many_attempts')]);
            }
        }

        $employee = $codes->find($validated['code']);

        if ($employee === null) {
            foreach ($keys as $key => [, $seconds]) {
                RateLimiter::hit($key, $seconds);
            }

            return back(303)->withErrors(['code' => __('attendance.errors.invalid_code')]);
        }

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $employee->id);
        $request->session()->put(self::FINGERPRINT_KEY, $codes->fingerprint($employee));
        $request->session()->put(self::EXPIRES_KEY, AlgerianDate::now()->addHours(self::SESSION_HOURS)->getTimestamp());

        return redirect()->route('work.show');
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->forget([self::SESSION_KEY, self::EXPIRES_KEY, self::FINGERPRINT_KEY]);
        $request->session()->regenerateToken();

        return redirect()->route('work.show');
    }

    public function checkIn(Request $request, StartSession $startSession): RedirectResponse
    {
        return $this->perform($request, function (Employee $employee) use ($startSession): string {
            $started = $startSession->handle($employee, AttendanceSource::Web);
            $at = $this->time($started->session->started_at);

            return (string) __($started->resumed ? 'attendance.replies.welcome_back' : 'attendance.replies.checked_in', ['time' => $at]);
        });
    }

    public function checkOut(Request $request, EndSession $endSession): RedirectResponse
    {
        return $this->perform($request, function (Employee $employee) use ($endSession): string {
            $session = $endSession->handle($employee);
            $minutes = WorkedTime::todayMinutes($employee);

            return (string) __('attendance.replies.checked_out', [
                'time' => $this->time($session->ended_at ?? AlgerianDate::now()),
                'total' => WorkedTime::format($minutes),
            ]);
        });
    }

    public function startBreak(Request $request, StartBreak $startBreak): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->perform($request, function (Employee $employee) use ($startBreak, $validated): string {
            $break = $startBreak->handle($employee, $validated['reason'], AttendanceSource::Web);

            return (string) __('attendance.replies.break_started', ['time' => $this->time($break->started_at)]);
        });
    }

    public function endBreak(Request $request, EndBreak $endBreak): RedirectResponse
    {
        return $this->perform($request, function (Employee $employee) use ($endBreak): string {
            $break = $endBreak->handle($employee);

            return (string) __('attendance.replies.welcome_back', ['time' => $this->time($break->ended_at ?? AlgerianDate::now())]);
        });
    }

    public function report(Request $request, AttendanceSession $attendanceSession, SaveSessionReport $saveReport): RedirectResponse
    {
        $validated = $request->validate([
            'report' => ['required', 'string', 'max:2000'],
            'blockers' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->perform($request, function (Employee $employee) use ($attendanceSession, $saveReport, $validated): string {
            $session = $this->ownSession($employee, $attendanceSession);
            $saveReport->handle($session, $validated['report'], $validated['blockers'] ?? null);

            return (string) __('attendance.replies.report_saved');
        });
    }

    public function correct(Request $request, AttendanceSession $attendanceSession, SubmitCorrection $submit): RedirectResponse
    {
        $validated = $request->validate([
            'ended_at' => ['required', 'string', 'max:5'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->perform($request, function (Employee $employee) use ($attendanceSession, $submit, $validated): string {
            $session = $this->ownSession($employee, $attendanceSession);
            $value = trim($validated['ended_at']);

            if (preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
                throw AttendanceException::invalidTimeFormat();
            }

            $proposedEnd = $session->started_at->setTimezone(AlgerianDate::TZ)->setTimeFromTimeString($value);

            if ($proposedEnd->lte($session->started_at->setTimezone(AlgerianDate::TZ))) {
                $proposedEnd = $proposedEnd->addDay();
            }

            $result = $submit->handle($employee, $session, CarbonImmutable::instance($proposedEnd), $validated['note'] ?? null);

            return (string) __(
                $result->applied ? 'attendance.replies.correction_applied' : 'attendance.replies.correction_pending',
                ['time' => $this->time($result->request->proposed_ended_at)],
            );
        });
    }

    /**
     * Runs one employee action. A rule the employee broke (already checked in,
     * no open session...) is shown as a message, not as an error page.
     *
     * @param  callable(Employee): string  $action
     */
    private function perform(Request $request, callable $action): RedirectResponse
    {
        $employee = $this->employee($request);

        if ($employee === null) {
            return redirect()->route('work.show')->with('error', __('attendance.errors.session_expired'));
        }

        try {
            $message = $action($employee);
        } catch (AttendanceException $exception) {
            return back(303)->with('error', $exception->messageForEmployee());
        }

        return back(303)->with('success', $message);
    }

    private function employee(Request $request): ?Employee
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $expires = (int) $request->session()->get(self::EXPIRES_KEY, 0);

        if (! is_int($id) || $expires < AlgerianDate::now()->getTimestamp()) {
            $this->forget($request);

            return null;
        }

        $employee = Employee::query()->find($id);
        $fingerprint = $employee === null ? null : app(AttendanceCode::class)->fingerprint($employee);

        // A replaced or removed code ends the sign-in made with the old one.
        if ($employee === null
            || ! $employee->isActive()
            || $fingerprint === null
            || ! hash_equals($fingerprint, (string) $request->session()->get(self::FINGERPRINT_KEY))) {
            $this->forget($request);

            return null;
        }

        return $employee;
    }

    private function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_KEY, self::EXPIRES_KEY, self::FINGERPRINT_KEY]);
    }

    private function ownSession(Employee $employee, AttendanceSession $session): AttendanceSession
    {
        if ($session->employee_id !== $employee->id) {
            throw AttendanceException::sessionNotFound();
        }

        return $session;
    }

    private function dashboard(Employee $employee, HoursBalance $balance): WorkDashboardData
    {
        $now = AlgerianDate::now()->utc();
        $hours = $balance->for($employee, $now);
        $paidBreak = AttendanceSetting::current()->paid_break_minutes;

        $open = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->open()
            ->with('breaks')
            ->first();
        $break = $open?->breaks->first(fn ($break): bool => $break->ended_at === null);

        $recent = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->with('breaks')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $recentMinutes = WorkedTime::minutesPerSession($recent, $now, $paidBreak);

        $correctionSession = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceStatus::AutoClosed)
            ->where('review_status', AttendanceReviewStatus::NeedsReview)
            ->whereDoesntHave('correctionRequests', fn ($query) => $query->pending())
            ->orderByDesc('started_at')
            ->first();

        $reportSession = AttendanceSession::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('ended_at')
            ->whereNull('report')
            ->where('ended_at', '>=', $now->subHours(SaveSessionReport::WINDOW_HOURS))
            ->orderByDesc('started_at')
            ->first();

        return new WorkDashboardData(
            name: $employee->name,
            state: match (true) {
                $open === null => 'offline',
                $break !== null => 'away',
                default => 'working',
            },
            serverNow: $now->toIso8601String(),
            sessionStartedAt: $open?->started_at->toIso8601String(),
            breakStartedAt: $break?->started_at->toIso8601String(),
            todayWorkedMinutes: $hours['today']['worked'],
            today: WorkHoursPeriodData::fromPeriod($hours['today']),
            week: WorkHoursPeriodData::fromPeriod($hours['week']),
            month: WorkHoursPeriodData::fromPeriod($hours['month']),
            recent: array_values($recent->map(fn (AttendanceSession $session): WorkSessionData => WorkSessionData::fromSession($session, $recentMinutes[$session->id] ?? 0))->all()),
            correction: $correctionSession === null ? null : [
                'id' => $correctionSession->id,
                'label' => $correctionSession->started_at->setTimezone(AlgerianDate::TZ)->format('D d M, H:i'),
                'endTime' => $this->time($correctionSession->ended_at ?? $now),
            ],
            reportPrompt: $reportSession === null ? null : [
                'id' => $reportSession->id,
                'label' => $reportSession->started_at->setTimezone(AlgerianDate::TZ)->format('D d M'),
            ],
        );
    }

    private function time(CarbonImmutable $at): string
    {
        return $at->setTimezone(AlgerianDate::TZ)->format('H:i');
    }
}
