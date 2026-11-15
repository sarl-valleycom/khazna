<?php

namespace App\Discord\Handlers\Attendance;

use App\Attendance\AttendanceException;
use App\Attendance\WorkedTime;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MyHoursHandler implements InteractionHandler
{
    use FindsEmployee;

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $now = AlgerianDate::now();
        $period = $interaction->option('period') ?? 'today';
        [$from, $to] = $this->range($period, $now);

        $total = WorkedTime::format(WorkedTime::rangeMinutes(
            $employee,
            $from,
            $to,
            $now,
            AttendanceSetting::current()->paid_break_minutes,
        ));

        $key = match ($period) {
            'week' => 'discord.my_hours.week',
            'month' => 'discord.my_hours.month',
            default => 'discord.my_hours.today',
        };

        return InteractionResponse::message(
            (string) __($key, ['total' => $total]),
            ephemeral: true,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $period, CarbonImmutable $now): array
    {
        return match ($period) {
            'week' => [
                $now->startOfWeek(CarbonInterface::SUNDAY)->utc(),
                $now->utc(),
            ],
            'month' => [
                $now->startOfMonth()->utc(),
                $now->utc(),
            ],
            default => [
                $now->startOfDay()->utc(),
                $now->endOfDay()->utc(),
            ],
        };
    }
}
