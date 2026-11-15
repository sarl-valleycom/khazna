<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\StartSession;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Handlers\Attendance\Concerns\SpeaksAttendance;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Support\AlgerianDate;

class CheckInHandler implements InteractionHandler
{
    use FindsEmployee, SpeaksAttendance;

    public function __construct(private readonly StartSession $startSession) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $started = $this->startSession->handle($employee);
        $now = AlgerianDate::now();

        if ($started->resumed) {
            $this->postEvent($employee, 'discord.events.back');

            return InteractionResponse::message(
                (string) __('discord.replies.welcome_back', [
                    'time' => $this->time($now),
                    'total' => $this->todayTotal($employee, $now),
                ]),
                ephemeral: true,
            );
        }

        $this->postEvent($employee, 'discord.events.checked_in', [
            'time' => $this->time($started->session->started_at),
        ]);

        return InteractionResponse::message(
            (string) __('discord.replies.checked_in', [
                'time' => $this->time($started->session->started_at),
                'total' => $this->todayTotal($employee, $now),
            ]),
            ephemeral: true,
        );
    }
}
