<?php

namespace App\Http\Controllers\Admin;

use App\Data\AuditLogRowData;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('view-audit-log');

        $causer = $request->integer('user') ?: null;
        $subject = $this->queryString($request, 'entity') ?: null;
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));
        $page = max(1, $request->integer('page', 1));
        $perPage = in_array($request->integer('per_page', 25), [10, 25, 50], true)
            ? $request->integer('per_page', 25)
            : 25;

        $query = Activity::query()
            ->with('causer')
            ->when($causer !== null, fn (Builder $query): Builder => $query->where('causer_id', $causer))
            ->when($subject !== null, fn (Builder $query): Builder => $query->where('subject_type', $subject));

        if ($from !== null) {
            $query->where('created_at', '>=', AlgerianDate::startOfDayUtc($from));
        }

        if ($to !== null) {
            $query->where('created_at', '<=', AlgerianDate::endOfDayUtc($to));
        }

        $query->latest('id');

        $total = (clone $query)->count();
        $rows = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Activity $activity): AuditLogRowData => AuditLogRowData::fromActivity($activity))
            ->all();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();

        $entities = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->all();

        return Inertia::render('admin/AuditLog', [
            'rows' => $rows,
            'total' => $total,
            'currentPage' => $page,
            'perPage' => $perPage,
            'filters' => [
                'user' => $causer,
                'entity' => $subject,
                'from' => $from,
                'to' => $to,
            ],
            'users' => $users,
            'entities' => $entities,
        ]);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? $value
            : null;
    }
}
