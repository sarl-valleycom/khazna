<?php

namespace App\Queries;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sessions that need a person to look at them: auto-closed, longer than twelve
 * hours, or overlapping another session for the same employee. Approved rows
 * leave the queue.
 */
class AttendanceReviewQuery
{
    /**
     * @return LengthAwarePaginator<int, AttendanceSession>
     */
    public function rows(?CarbonImmutable $now = null, int $perPage = 25): LengthAwarePaginator
    {
        $now = ($now ?? AlgerianDate::now())->utc();

        $autoClosed = $this->autoClosedIds();
        $long = $this->longIds($now);
        $overlapping = $this->overlappingIds();

        $ids = array_values(array_unique([...$autoClosed, ...$long, ...$overlapping]));
        $reasons = $this->reasons($autoClosed, $long, $overlapping);

        $rows = AttendanceSession::query()
            ->with(['employee:id,name,office_id', 'employee.office:id,name', 'breaks'])
            ->when(
                $ids === [],
                fn ($query) => $query->whereRaw('1 = 0'),
                fn ($query) => $query->whereIn('id', $ids),
            )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $rows->getCollection()->each(
            fn (AttendanceSession $session) => $session->setAttribute('review_reasons', $reasons[$session->id] ?? []),
        );

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function autoClosedIds(): array
    {
        return array_values(AttendanceSession::query()
            ->where('status', AttendanceStatus::AutoClosed)
            ->where('review_status', AttendanceReviewStatus::NeedsReview)
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all());
    }

    /**
     * @return list<int>
     */
    private function longIds(CarbonImmutable $now): array
    {
        return array_values(AttendanceSession::query()
            ->where('review_status', '!=', AttendanceReviewStatus::Approved)
            ->whereRaw(
                'TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, ?)) > ?',
                [$now->toDateTimeString(), AttendanceSession::LONG_SESSION_SECONDS],
            )
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all());
    }

    /**
     * @return list<int>
     */
    private function overlappingIds(): array
    {
        return array_values(AttendanceSession::query()
            ->where('review_status', '!=', AttendanceReviewStatus::Approved)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('attendance_sessions as other')
                    ->whereColumn('other.employee_id', 'attendance_sessions.employee_id')
                    ->whereColumn('other.id', '!=', 'attendance_sessions.id')
                    ->whereNull('other.deleted_at')
                    ->whereRaw('attendance_sessions.started_at < COALESCE(other.ended_at, ?)', ['9999-12-31 23:59:59'])
                    ->whereRaw('COALESCE(attendance_sessions.ended_at, ?) > other.started_at', ['9999-12-31 23:59:59']);
            })
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
