<?php

namespace App\Data;

use App\Models\AttendanceSetting;
use App\Models\Office;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendanceSettingsData extends Data
{
    /**
     * @param  list<OfficeAttendanceChannelData>  $offices
     */
    public function __construct(
        public string $autoCloseTime,
        public string $digestTime,
        public bool $remindersEnabled,
        public int $reminderAfterMinutes,
        public int $awayLimitMinutes,
        public int $paidBreakMinutes,
        public bool $selfCorrectionEnabled,
        public int $selfCorrectionHours,
        public bool $over10hReminderEnabled,
        public ?string $ownerDigestChannelId,
        public ?string $reportsChannelId,
        public array $offices,
    ) {}

    public static function fromCurrent(): self
    {
        $settings = AttendanceSetting::current();

        return new self(
            autoCloseTime: substr($settings->auto_close_time, 0, 5),
            digestTime: substr($settings->digest_time, 0, 5),
            remindersEnabled: $settings->reminders_enabled,
            reminderAfterMinutes: $settings->reminder_after_minutes,
            awayLimitMinutes: $settings->away_limit_minutes,
            paidBreakMinutes: $settings->paid_break_minutes,
            selfCorrectionEnabled: $settings->self_correction_enabled,
            selfCorrectionHours: $settings->self_correction_hours,
            over10hReminderEnabled: $settings->over_10h_reminder_enabled,
            ownerDigestChannelId: $settings->owner_digest_channel_id,
            reportsChannelId: $settings->reports_channel_id,
            offices: array_values(Office::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Office $office): OfficeAttendanceChannelData => OfficeAttendanceChannelData::fromOffice($office))
                ->all()),
        );
    }
}
