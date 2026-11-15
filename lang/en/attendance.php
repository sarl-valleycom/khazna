<?php

return [

    'errors' => [
        'inactive' => 'Your employee record is deactivated. Nothing was recorded.',
        'no_open_session' => 'You have no open session.',
        'already_checked_in' => 'You are already checked in since :time.',
        'already_away' => 'You are already away since :time.',
        'not_away' => 'You are not away.',
        'reason_required' => 'A reason is required.',
        'session_not_found' => 'That session was not found.',
        'not_closed' => 'You can only correct a closed session.',
        'invalid_end_time' => 'The end time must be after the start time and not in the future.',
        'invalid_time_format' => 'Use HH:MM in Algiers time.',
        'not_checked_out' => 'Check out before writing your report.',
        'report_required' => 'Write what you did today.',
        'invalid_code' => 'That code is not right. Check it with the owner.',
        'too_many_attempts' => 'Too many tries. Wait a minute and try again.',
        'session_expired' => 'Enter your code again.',
    ],

    'replies' => [
        'checked_in' => 'Checked in at :time.',
        'welcome_back' => 'Welcome back at :time.',
        'break_started' => 'Break started at :time.',
        'checked_out' => 'Checked out at :time. Worked :total today.',
        'correction_applied' => 'End time corrected to :time. Thank you.',
        'correction_pending' => 'Thanks. Your correction is waiting for the owner to approve.',
        'report_saved' => 'Thanks, your report is saved.',
    ],

];
