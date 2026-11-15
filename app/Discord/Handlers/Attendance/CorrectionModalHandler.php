<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\SubmitCorrection;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;

class CorrectionModalHandler implements InteractionHandler
{
    use FindsEmployee;

    public function __construct(private readonly SubmitCorrection $submitCorrection) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $session = $this->sessionFor($interaction, $employee->id);

        $value = trim((string) $interaction->componentValue('ended_at'));

        if (preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            throw AttendanceException::invalidTimeFormat();
        }

        $proposedEnd = $session->started_at
            ->setTimezone(AlgerianDate::TZ)
            ->setTimeFromTimeString($value);

        if ($proposedEnd->lte($session->started_at->setTimezone(AlgerianDate::TZ))) {
            $proposedEnd = $proposedEnd->addDay();
        }

        $result = $this->submitCorrection->handle(
            $employee,
            $session,
            CarbonImmutable::instance($proposedEnd),
            $interaction->componentValue('note'),
        );

        return InteractionResponse::message(
            (string) __(
                $result->applied ? 'discord.replies.correction_applied' : 'discord.replies.correction_pending',
                [
                    'time' => $result->request->proposed_ended_at->setTimezone(AlgerianDate::TZ)->format('H:i'),
                ],
            ),
            ephemeral: true,
        );
    }

    private function sessionFor(Interaction $interaction, int $employeeId): AttendanceSession
    {
        $customId = (string) $interaction->customId();
        $id = (int) substr($customId, strlen(OpenCorrectionModalHandler::PREFIX));

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
