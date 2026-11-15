<?php

namespace App\Console\Commands;

use App\Actions\Attendance\AutoCloseSession;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Runs every minute. It closes the sessions that were still open at the most
 * recent auto-close time, so a late or restarted scheduler catches up on the
 * next tick instead of skipping the night.
 */
class AttendanceAutoCloseCommand extends Command
{
    protected $signature = 'attendance:auto-close {--all : Close every open session now, whatever the time}';

    protected $description = 'Close sessions that were still open at the configured auto-close time';

    public function handle(AutoCloseSession $action): int
    {
        $now = AlgerianDate::now();
        $cutoff = $this->latestCutoff($now);
        $closed = 0;

        AttendanceSession::query()
            ->open()
            ->when(! $this->option('all'), fn ($query) => $query->where('started_at', '<=', $cutoff->utc()))
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use ($action, $now, &$closed): void {
                foreach ($sessions as $session) {
                    $action->handle($session, $now);
                    $closed++;
                }
            });

        if ($closed > 0 || $this->option('all')) {
            $this->info("Auto-closed {$closed} session(s).");
        }

        return self::SUCCESS;
    }

    /** Today's auto-close time if it has passed, otherwise yesterday's. */
    private function latestCutoff(CarbonImmutable $now): CarbonImmutable
    {
        $time = substr(AttendanceSetting::current()->auto_close_time, 0, 5);
        $cutoff = CarbonImmutable::parse($now->toDateString().' '.$time, AlgerianDate::TZ);

        return $now->lt($cutoff) ? $cutoff->subDay() : $cutoff;
    }
}
