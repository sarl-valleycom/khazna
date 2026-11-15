<?php

namespace App\Data;

use App\Attendance\WorkedTime;
use App\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MonthlySummaryRowData extends Data
{
    public function __construct(
        public int $employeeId,
        public string $name,
        public string $officeName,
        public int $expectedMinutes,
        public int $workedMinutes,
        public int $toMakeUpMinutes,
        public int $extraMinutes,
        public string $expectedFormatted,
        public string $workedFormatted,
        public string $toMakeUpFormatted,
        public string $extraFormatted,
        public int $daysWorked,
        public int $daysOff,
        public int $notChecked,
        public string $sessionsUrl,
    ) {}

    /**
     * @param  array<string, int>  $summary
     */
    public static function fromSummary(Employee $employee, array $summary, string $sessionsUrl): self
    {
        return new self(
            employeeId: $employee->id,
            name: $employee->name,
            officeName: $employee->office->name ?? '',
            expectedMinutes: $summary['expected'],
            workedMinutes: $summary['worked'],
            toMakeUpMinutes: $summary['to_make_up'],
            extraMinutes: $summary['extra'],
            expectedFormatted: WorkedTime::format($summary['expected']),
            workedFormatted: WorkedTime::format($summary['worked']),
            toMakeUpFormatted: WorkedTime::format($summary['to_make_up']),
            extraFormatted: WorkedTime::format($summary['extra']),
            daysWorked: $summary['days_worked'],
            daysOff: $summary['days_off'],
            notChecked: $summary['not_checked'],
            sessionsUrl: $sessionsUrl,
        );
    }
}
