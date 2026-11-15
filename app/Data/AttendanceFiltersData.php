<?php

namespace App\Data;

use App\Enums\AttendanceStatus;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendanceFiltersData extends Data
{
    public function __construct(
        public string $q = '',
        public ?int $employee = null,
        public ?int $office = null,
        public string $status = '',
        public ?string $from = null,
        public ?string $to = null,
        public string $sort = 'startedAt',
        public string $direction = 'desc',
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString();
        $perPage = $request->integer('per_page', 10);
        $status = $request->string('status')->toString();

        return new self(
            q: trim($request->string('q')->toString()),
            employee: $request->integer('employee') ?: null,
            office: $request->integer('office') ?: null,
            status: in_array($status, AttendanceStatus::values(), true) ? $status : '',
            from: self::date($request->query('from')),
            to: self::date($request->query('to')),
            sort: in_array($sort, ['startedAt', 'employeeName', 'status'], true) ? $sort : 'startedAt',
            direction: $direction === 'asc' ? 'asc' : 'desc',
            perPage: in_array($perPage, [10, 25, 50], true) ? $perPage : 10,
        );
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? $value
            : null;
    }
}
