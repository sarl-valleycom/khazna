<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\SaveSessionReport;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Models\AttendanceSession;

class ReportModalHandler implements InteractionHandler
{
    use FindsEmployee;

    public function __construct(private readonly SaveSessionReport $saveReport) {}

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $session = $this->sessionFor($interaction, $employee->id);

        $this->saveReport->handle(
            $session,
            (string) $interaction->componentValue('report'),
            $interaction->componentValue('blockers'),
        );

        return InteractionResponse::message(
            (string) __('discord.replies.report_saved'),
            ephemeral: true,
        );
    }

    private function sessionFor(Interaction $interaction, int $employeeId): AttendanceSession
    {
        $customId = (string) $interaction->customId();
        $id = (int) substr($customId, strlen(OpenReportModalHandler::PREFIX));

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
