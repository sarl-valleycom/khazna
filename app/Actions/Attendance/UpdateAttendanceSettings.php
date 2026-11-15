<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceSetting;
use App\Models\Office;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceSettings
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): void
    {
        DB::transaction(function () use ($data): void {
            app(AuditContext::class)->withReason(__('Attendance settings updated by the owner'), function () use ($data): void {
                AttendanceSetting::current()->update([
                    'auto_close_time' => self::time($data['auto_close_time']),
                    'reminders_enabled' => (bool) $data['reminders_enabled'],
                    'reminder_after_minutes' => (int) $data['reminder_after_minutes'],
                    'away_limit_minutes' => (int) $data['away_limit_minutes'],
                    'paid_break_minutes' => (int) $data['paid_break_minutes'],
                    'self_correction_enabled' => (bool) $data['self_correction_enabled'],
                    'self_correction_hours' => (int) $data['self_correction_hours'],
                    'over_10h_reminder_enabled' => (bool) $data['over_10h_reminder_enabled'],
                    'reports_channel_id' => self::channel($data['reports_channel_id'] ?? null),
                ]);

                foreach ($data['offices'] ?? [] as $row) {
                    $office = Office::query()->whereKey($row['id'])->first();

                    if ($office === null) {
                        continue;
                    }

                    $office->update([
                        'attendance_channel_id' => self::channel($row['attendance_channel_id'] ?? null),
                    ]);
                }
            });
        });
    }

    private static function time(mixed $value): string
    {
        return substr((string) $value, 0, 5).':00';
    }

    private static function channel(mixed $value): ?string
    {
        $value = $value === null ? null : trim((string) $value);

        return $value === '' ? null : $value;
    }
}
