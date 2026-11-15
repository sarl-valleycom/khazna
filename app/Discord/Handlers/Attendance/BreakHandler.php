<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\StartBreak;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Handlers\Attendance\Concerns\SpeaksAttendance;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Support\AlgerianDate;

class BreakHandler implements InteractionHandler
{
    use FindsEmployee, SpeaksAttendance;

    public function __construct(private readonly StartBreak $startBreak) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $reason = $interaction->option('reason');

        if ($reason === null || trim($reason) === '') {
            throw AttendanceException::reasonRequired();
        }

        $employee = $this->employeeFor($userId);
        $break = $this->startBreak->handle($employee, $reason);
        $now = AlgerianDate::now();

        $this->postEvent($employee, 'discord.events.break_started', [
            'time' => $this->time($break->started_at),
            'reason' => $break->reason ?? '',
        ]);

        return InteractionResponse::message(
            (string) __('discord.replies.break_started', [
                'time' => $this->time($break->started_at),
                'total' => $this->todayTotal($employee, $now),
            ]),
            ephemeral: true,
            components: [
                InteractionResponse::actionRow([
                    InteractionResponse::button(
                        (string) __('discord.labels.im_back'),
                        'attendance:back',
                    ),
                ]),
            ],
        );
    }
}
