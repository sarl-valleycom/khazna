<?php

namespace App\Console\Commands;

use App\Actions\Attendance\AutoCloseSession;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Illuminate\Console\Command;

class AttendanceAutoCloseCommand extends Command
{
    protected $signature = 'attendance:auto-close';

    protected $description = 'Close sessions still open at the configured auto-close time';

    public function handle(AutoCloseSession $action): int
    {
        $now = AlgerianDate::now();
        $closed = 0;

        AttendanceSession::query()
            ->open()
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use ($action, $now, &$closed): void {
                foreach ($sessions as $session) {
                    $action->handle($session, $now);
                    $closed++;
                }
            });

        $this->info("Auto-closed {$closed} session(s).");

        return self::SUCCESS;
    }
}
