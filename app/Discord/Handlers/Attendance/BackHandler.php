<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\EndBreak;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Handlers\Attendance\Concerns\SpeaksAttendance;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Support\AlgerianDate;

class BackHandler implements InteractionHandler
{
    use FindsEmployee, SpeaksAttendance;

    public function __construct(private readonly EndBreak $endBreak) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $break = $this->endBreak->handle($employee);
        $now = AlgerianDate::now();

        $this->postEvent($employee, 'discord.events.back', [
            'time' => $this->time($break->ended_at ?? $now),
        ]);

        return InteractionResponse::message(
            (string) __('discord.replies.welcome_back', [
                'time' => $this->time($break->ended_at ?? $now),
                'total' => $this->todayTotal($employee, $now),
            ]),
            ephemeral: true,
        );
    }
}
