<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceSetting;
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
                    'paid_break_minutes' => (int) $data['paid_break_minutes'],
                    'self_correction_enabled' => (bool) $data['self_correction_enabled'],
                    'self_correction_hours' => (int) $data['self_correction_hours'],
                ]);
            });
        });
    }

    private static function time(mixed $value): string
    {
        return substr((string) $value, 0, 5).':00';
    }
}
