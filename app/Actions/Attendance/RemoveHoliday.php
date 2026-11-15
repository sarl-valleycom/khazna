<?php

namespace App\Actions\Attendance;

use App\Models\Holiday;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

class RemoveHoliday
{
    public function handle(Holiday $holiday, string $reason): void
    {
        DB::transaction(function () use ($holiday, $reason): void {
            app(AuditContext::class)->withReason($reason, fn () => $holiday->delete());
        });
    }
}
