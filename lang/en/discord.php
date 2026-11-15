<?php

return [

    'errors' => [
        'unknown_interaction' => 'That command is not available.',
        'generic' => 'Something went wrong. Please try again.',
        'not_linked' => 'Your Discord account is not linked to an employee. Ask the owner to link it.',
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
    ],

    'replies' => [
        'checked_in' => 'Checked in at :time. Today: :total.',
        'welcome_back' => 'Welcome back at :time. Today: :total.',
        'break_started' => 'Break started at :time. Today: :total.',
        'checked_out' => 'Checked out at :time. Worked :total today.',
        'correction_applied' => 'End time corrected to :time. Thank you.',
        'correction_pending' => 'Thanks. Your correction is waiting for the owner to approve.',
    ],

    'events' => [
        'checked_in' => ':mention checked in at :time',
        'checked_out' => ':mention checked out at :time',
        'break_started' => ':mention stepped away at :time (:reason)',
        'back' => ':mention is back at :time',
    ],

    'labels' => [
        'im_back' => 'I\'m back',
        'correct_end_time' => 'Correct end time',
        'real_end_time' => 'Real end time (HH:MM)',
        'note_optional' => 'Note (optional)',
    ],

    'corrections' => [
        'dm' => 'Your session on :date was closed automatically at :time. If that is not right, correct your end time.',
        'modal_title' => 'Correct your end time',
        'long_session' => 'You have been working since :time, over 10 hours. Did you forget to check out?',
    ],

    'commands' => [
        'check_in' => 'Start a work session',
        'check_out' => 'End your work session',
        'tmp_check_out' => 'Take a break inside your work session',
        'tmp_check_out_reason' => 'Why are you stepping away?',
        'status' => 'Show your current state and today\'s total',
        'my_hours' => 'Show your own worked hours for a period',
        'my_hours_period' => 'The period to show',
    ],

];
