<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. Calendar dates are Algiers calendar dates.
    | Everything shown to a user is rendered in this timezone.
    |
    */

    'display_timezone' => 'Africa/Algiers',

    /*
    |--------------------------------------------------------------------------
    | Week start
    |--------------------------------------------------------------------------
    |
    | Algerian weeks run Saturday to Friday.
    |
    */

    'week_starts_on' => 'saturday',

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    |
    | Amounts are stored as integer centimes. DZD is the only currency at
    | launch; the column exists so another currency can be added later.
    |
    */

    'currency' => 'DZD',
    'currency_symbol' => 'DA',
    'money_scale' => 2,

    /*
    |--------------------------------------------------------------------------
    | Salary visibility
    |--------------------------------------------------------------------------
    |
    | Salaries are owner-only by default. The owner may later grant finance
    | access from settings (FR-AUTH-05).
    |
    */

    'finance_can_view_salaries' => env('KHAZNA_FINANCE_CAN_VIEW_SALARIES', false),

    'reminder_template' => 'Hello {client}, this is a friendly reminder that {amount} for {contract} is due on {date}. Thank you.',

    'salary_due_day' => 5,

    // The company that owns and runs this workspace (Khazna is the product name).
    'company' => env('KHAZNA_COMPANY', 'Valleycom'),

];
