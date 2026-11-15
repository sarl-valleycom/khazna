<?php

/*
 * Only the attribute names live here; the messages themselves come from the framework.
 * Form fields use database-style keys ("amount_cents", "account_manager_id"); these
 * give them the names people see on screen, so errors do not leak internals.
 */
return [
    'attributes' => [
        'account_id' => 'account',
        'account_manager_id' => 'account manager',
        'amount_cents' => 'amount',
        'category_id' => 'category',
        'client_id' => 'client',
        'counted_balance_cents' => 'counted balance',
        'employee_id' => 'employee',
        'fee_cents' => 'fee',
        'from_account_id' => 'source account',
        'office_id' => 'office',
        'opening_balance_cents' => 'opening balance',
        'salary_cents' => 'salary',
        'supplier_id' => 'supplier',
        'to_account_id' => 'destination account',
        'due_on' => 'due date',
        'new_due_on' => 'new due date',
        'paid_on' => 'payment date',
        'starts_on' => 'start date',
        'ends_on' => 'end date',
        'started_at' => 'start time',
        'ended_at' => 'end time',
    ],
];
