<?php

namespace App\Discord\Handlers\Attendance;

use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Models\AttendanceSession;

class OpenCorrectionModalHandler implements InteractionHandler
{
    use FindsEmployee;

    public const PREFIX = 'attendance:correct:';

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $session = $this->sessionFor($interaction, $employee->id);

        return InteractionResponse::modal(
            self::PREFIX.$session->id,
            (string) __('discord.corrections.modal_title'),
            [
                InteractionResponse::actionRow([
                    InteractionResponse::textInput(
                        'ended_at',
                        (string) __('discord.labels.real_end_time'),
                        required: true,
                        style: 1,
                        maxLength: 5,
                    ),
                ]),
                InteractionResponse::actionRow([
                    InteractionResponse::textInput(
                        'note',
                        (string) __('discord.labels.note_optional'),
                        required: false,
                        style: 2,
                        maxLength: 500,
                    ),
                ]),
            ],
        );
    }

    private function sessionFor(Interaction $interaction, int $employeeId): AttendanceSession
    {
        $customId = (string) $interaction->customId();
        $id = (int) substr($customId, strlen(self::PREFIX));

        $session = AttendanceSession::query()
            ->whereKey($id)
            ->where('employee_id', $employeeId)
            ->first();

        if ($session === null) {
            throw AttendanceException::sessionNotFound();
        }

        return $session;
    }
}
