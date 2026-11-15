<?php

namespace App\Attendance;

use App\Models\AttendanceSession;

final class StartedSession
{
    public function __construct(
        public readonly AttendanceSession $session,
        public readonly bool $resumed,
    ) {}
}
