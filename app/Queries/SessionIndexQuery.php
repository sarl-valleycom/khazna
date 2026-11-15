<?php

namespace App\Queries;

use App\Data\AttendanceFiltersData;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The owner's attendance list. Every date boundary is an Algiers calendar day,
 * while the stored timestamps stay UTC.
 */
class SessionIndexQuery
{
    /**
     * The screen's page of sessions.
     *
     * @return LengthAwarePaginator<int, AttendanceSession>
     */
    public function rows(AttendanceFiltersData $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters->perPage)->withQueryString();
    }

    /**
     * Every session the filters match, in the screen's order. The Excel export
     * walks this same builder.
     *
     * @return Builder<AttendanceSession>
     */
    public function query(AttendanceFiltersData $filters): Builder
    {
        $query = AttendanceSession::query()
            ->with(['employee:id,name,office_id', 'employee.office:id,name', 'breaks'])
            ->when($filters->q !== '', fn (Builder $query): Builder => $query->whereHas(
                'employee',
                fn (Builder $employee): Builder => $employee->where('name', 'like', '%'.$filters->q.'%'),
            ))
            ->when($filters->employee !== null, fn (Builder $query): Builder => $query->where('employee_id', $filters->employee))
            ->when($filters->office !== null, fn (Builder $query): Builder => $query->whereHas(
                'employee',
                fn (Builder $employee): Builder => $employee->where('office_id', $filters->office),
            ))
            ->when($filters->status !== '', fn (Builder $query): Builder => $query->where('status', $filters->status));

        if ($filters->from !== null) {
            $query->where('started_at', '>=', AlgerianDate::startOfDayUtc($filters->from));
        }

        if ($filters->to !== null) {
            $query->where('started_at', '<=', AlgerianDate::endOfDayUtc($filters->to));
        }

        $this->sort($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     */
    private function sort(Builder $query, AttendanceFiltersData $filters): void
    {
        $direction = $filters->direction === 'asc' ? 'asc' : 'desc';

        match ($filters->sort) {
            'employeeName' => $query->orderBy(
                Employee::query()
                    ->select('name')
                    ->whereColumn('employees.id', 'attendance_sessions.employee_id'),
                $direction,
            ),
            'status' => $query->orderBy('attendance_sessions.status', $direction),
            default => $query->orderBy('attendance_sessions.started_at', $direction),
        };

        $query->orderByDesc('attendance_sessions.id');
    }
}
