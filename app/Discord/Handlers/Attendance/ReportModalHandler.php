<?php

namespace App\Discord\Handlers\Attendance;

use App\Actions\Attendance\SaveSessionReport;
use App\Attendance\AttendanceException;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Jobs\SendAttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Illuminate\Support\Facades\Log;

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

        $saved = $this->saveReport->handle(
            $session,
            (string) $interaction->componentValue('report'),
            $interaction->componentValue('blockers'),
        );

        $this->postToReportsChannel($saved, $employee->discord_user_id);

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

    /**
     * Posts the finished report to the reports channel through a queued job, so
     * a Discord outage never fails the save. With no channel set, the report is
     * still saved on the session.
     */
    private function postToReportsChannel(AttendanceSession $session, ?string $discordUserId): void
    {
        $channelId = (string) (AttendanceSetting::current()->reports_channel_id
            ?? config('discord.channels.reports')
            ?? '');

        if ($channelId === '') {
            Log::info('End-of-day report not posted: no reports channel is set.', ['session_id' => $session->id]);

            return;
        }

        $message = (string) __('discord.report.post', [
            'mention' => $discordUserId === null ? (string) $session->employee?->name : '<@'.$discordUserId.'>',
            'date' => $session->started_at->setTimezone(AlgerianDate::TZ)->format('d M Y'),
        ])."\n".trim((string) $session->report);

        if ($session->blockers !== null && $session->blockers !== '') {
            $message .= "\n".__('discord.report.post_blockers', ['blockers' => $session->blockers]);
        }

        // Discord rejects a message over 2000 characters.
        SendAttendanceEvent::dispatch($channelId, mb_substr($message, 0, 1900));
    }
}
