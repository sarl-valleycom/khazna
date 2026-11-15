<?php

namespace App\Actions\Attendance;

use App\Models\LeaveRequest;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

class CancelLeave
{
    public function handle(LeaveRequest $leave, string $reason): void
    {
        DB::transaction(function () use ($leave, $reason): void {
            app(AuditContext::class)->withReason($reason, fn () => $leave->delete());
        });
    }
}
