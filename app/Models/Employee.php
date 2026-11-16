<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * There is deliberately no salary attribute: the salary in force on a date is
 * the latest salary history row on or before it (see salaryOn()). Nothing that
 * lists or serialises employees can therefore carry a salary by accident.
 *
 * @property int $id
 * @property int $office_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $title
 * @property CarbonImmutable|null $hire_date
 * @property EmployeeStatus $status
 * @property CarbonImmutable|null $left_on
 * @property EmploymentType $contract_type
 * @property int|null $hourly_rate
 * @property int $expected_minutes_per_day
 * @property array<int, int>|null $work_days
 * @property string|null $schedule_start
 * @property string|null $schedule_end
 * @property string|null $attendance_code_hash
 * @property string|null $attendance_code_encrypted
 * @property CarbonImmutable|null $attendance_code_set_at
 */
#[Fillable([
    'office_id', 'name', 'phone', 'title', 'hire_date', 'status', 'left_on',
    'contract_type', 'hourly_rate', 'expected_minutes_per_day',
    'work_days', 'schedule_start', 'schedule_end',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory, HasVisibility, SoftDeletes {
        Auditable::auditSecurityAttributes as private baseAuditSecurityAttributes;
    }

    /** @var list<string> */
    protected $hidden = ['attendance_code_hash', 'attendance_code_encrypted'];

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'hire_date' => 'immutable_date',
            'left_on' => 'immutable_date',
            'status' => EmployeeStatus::class,
            'contract_type' => EmploymentType::class,
            'hourly_rate' => 'integer',
            'expected_minutes_per_day' => 'integer',
            'work_days' => 'array',
            'attendance_code_set_at' => 'immutable_datetime',
        ];
    }

    /**
     * Changing the attendance code leaves an audit row (who and when) without
     * ever logging the hash.
     *
     * @return list<string>
     */
    protected function auditSecurityAttributes(): array
    {
        return [...$this->baseAuditSecurityAttributes(), 'attendance_code_hash', 'attendance_code_encrypted'];
    }

    public function hasAttendanceCode(): bool
    {
        return $this->attendance_code_hash !== null;
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<EmployeeSalary, $this> */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /** @return HasMany<SalaryAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /** @return HasMany<AttendanceSession, $this> */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** The monthly salary in force on a date: the latest raise on or before it. Null before the first one. */
    public function salaryOn(CarbonInterface|string $date): ?int
    {
        $value = $this->salaries()
            ->whereDate('effective_from', '<=', $date instanceof CarbonInterface ? $date->toDateString() : $date)
            ->orderByDesc('effective_from')
            ->value('amount_cents');

        return $value === null ? null : (int) $value;
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    /**
     * Work days as Carbon day-of-week numbers (Sunday 0 … Saturday 6). The
     * default is the Algerian work week, Sunday to Thursday (BR-09).
     *
     * @return list<int>
     */
    public function workDays(): array
    {
        $days = $this->work_days;

        if (! is_array($days) || $days === []) {
            return [0, 1, 2, 3, 4];
        }

        return array_values(array_map('intval', $days));
    }

    public function worksOn(CarbonInterface|string $date): bool
    {
        return in_array(AlgerianDate::toAlgiers($date)->dayOfWeek, $this->workDays(), true);
    }

    /**
     * How many of this person's work days fall between two dates, counted by
     * whole weeks plus at most six leftover days, so a range of any length costs
     * the same.
     */
    public function countWorkDays(CarbonInterface|string $from, CarbonInterface|string $to): int
    {
        $start = AlgerianDate::toAlgiers($from)->startOfDay();
        $end = AlgerianDate::toAlgiers($to)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        $days = (int) $start->diffInDays($end) + 1;
        $count = intdiv($days, 7) * count($this->workDays());

        for ($offset = 0; $offset < $days % 7; $offset++) {
            if ($this->worksOn($start->addDays(intdiv($days, 7) * 7 + $offset))) {
                $count++;
            }
        }

        return $count;
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Employees are soft-deleted and cannot be permanently deleted.'));
    }
}
