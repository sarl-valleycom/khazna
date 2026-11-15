<?php

namespace App\Data;

use App\Models\AttendanceSetting;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendanceSettingsData extends Data
{
    public function __construct(
        public string $autoCloseTime,
        public int $paidBreakMinutes,
        public bool $selfCorrectionEnabled,
        public int $selfCorrectionHours,
    ) {}

    public static function fromCurrent(): self
    {
        $settings = AttendanceSetting::current();

        return new self(
            autoCloseTime: substr($settings->auto_close_time, 0, 5),
            paidBreakMinutes: $settings->paid_break_minutes,
            selfCorrectionEnabled: $settings->self_correction_enabled,
            selfCorrectionHours: $settings->self_correction_hours,
        );
    }
}
