<?php

namespace App\Queries;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sessions that need a person to look at them: auto-closed, longer than twelve
 * hours, or overlapping another session for the same employee. Approved rows
 * leave the queue.
 */
class AttendanceReviewQuery
{
    /**
     * One query, paginated in SQL. The reasons are worked out afterwards for
     * the rows on the page only, so the cost follows the page size and not the
     * length of the attendance history.
     *
     * @return LengthAwarePaginator<int, AttendanceSession>
     */
    public function rows(?CarbonImmutable $now = null, int $perPage = 25): LengthAwarePaginator
    {
        $now = ($now ?? AlgerianDate::now())->utc();

        $rows = AttendanceSession::query()
            ->with(['employee:id,name,office_id', 'employee.office:id,name', 'breaks'])
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where(fn (Builder $auto) => $this->autoClosed($auto))
                    ->orWhere(fn (Builder $long) => $this->long($long, $now))
                    ->orWhere(fn (Builder $overlap) => $this->overlapping($overlap));
            })
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $pageIds = $rows->getCollection()->map(fn (AttendanceSession $session): int => $session->id)->all();

        $autoClosed = $this->idsOnPage($pageIds, fn (Builder $query) => $this->autoClosed($query));
        $long = $this->idsOnPage($pageIds, fn (Builder $query) => $this->long($query, $now));
        $overlapping = $this->idsOnPage($pageIds, fn (Builder $query) => $this->overlapping($query));

        $reasons = $this->reasons($autoClosed, $long, $overlapping);

        $rows->getCollection()->each(
            fn (AttendanceSession $session) => $session->setAttribute('review_reasons', $reasons[$session->id] ?? []),
        );

        return $rows;
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     */
    private function autoClosed(Builder $query): void
    {
        $query
            ->where('status', AttendanceStatus::AutoClosed)
            ->where('review_status', AttendanceReviewStatus::NeedsReview);
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     */
    private function long(Builder $query, CarbonImmutable $now): void
    {
        $query
            ->where('review_status', '!=', AttendanceReviewStatus::Approved)
            ->whereRaw(
                'TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, ?)) > ?',
                [$now->toDateTimeString(), AttendanceSession::LONG_SESSION_SECONDS],
            );
    }

    /**
     * @param  Builder<AttendanceSession>  $query
     */
    private function overlapping(Builder $query): void
    {
        $query
            ->where('review_status', '!=', AttendanceReviewStatus::Approved)
            ->whereExists(function ($exists): void {
                $exists->selectRaw('1')
                    ->from('attendance_sessions as other')
                    ->whereColumn('other.employee_id', 'attendance_sessions.employee_id')
                    ->whereColumn('other.id', '!=', 'attendance_sessions.id')
                    ->whereNull('other.deleted_at')
                    ->whereRaw('attendance_sessions.started_at < COALESCE(other.ended_at, ?)', ['9999-12-31 23:59:59'])
                    ->whereRaw('COALESCE(attendance_sessions.ended_at, ?) > other.started_at', ['9999-12-31 23:59:59']);
            });
    }

    /**
     * Which of the page's sessions match one condition.
     *
     * @param  array<int, int>  $pageIds
     * @param  callable(Builder<AttendanceSession>): void  $condition
     * @return list<int>
     */
    private function idsOnPage(array $pageIds, callable $condition): array
    {
        if ($pageIds === []) {
            return [];
        }

        return array_values(AttendanceSession::query()
            ->whereIn('id', $pageIds)
            ->where(fn (Builder $query) => $condition($query))
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all());
    }

    /**
     * @param  list<int>  $autoClosed
     * @param  list<int>  $long
     * @param  list<int>  $overlapping
     * @return array<int, list<string>>
     */
    private function reasons(array $autoClosed, array $long, array $overlapping): array
    {
        $reasons = [];

        foreach ($autoClosed as $id) {
            $reasons[$id][] = (string) __('Auto-closed and waiting for review.');
        }

        foreach ($long as $id) {
            $reasons[$id][] = (string) __('Longer than 12 hours.');
        }

        foreach ($overlapping as $id) {
            $reasons[$id][] = (string) __('Overlaps another session.');
        }

        return $reasons;
    }
}
