<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\EndSession;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Handlers\Attendance\Concerns\SpeaksAttendance;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Support\AlgerianDate;

class CheckOutHandler implements InteractionHandler
{
    use FindsEmployee, SpeaksAttendance;

    public function __construct(private readonly EndSession $endSession) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $session = $this->endSession->handle($employee);
        $now = AlgerianDate::now();

        $this->postEvent($employee, 'discord.events.checked_out', [
            'time' => $this->time($session->ended_at ?? $now),
        ]);

        return InteractionResponse::message(
            (string) __('discord.replies.checked_out', [
                'time' => $this->time($session->ended_at ?? $now),
                'total' => $this->todayTotal($employee, $now),
            ]),
            ephemeral: true,
            components: [
                InteractionResponse::actionRow([
                    InteractionResponse::button(
                        (string) __('discord.labels.write_report'),
                        OpenReportModalHandler::PREFIX.$session->id,
                    ),
                ]),
            ],
        );
    }
}
