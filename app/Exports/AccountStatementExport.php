<?php

namespace App\Exports;

use App\Models\Account;
use App\Models\User;
use App\Queries\AccountStatementQuery;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * An account statement as a workbook: a Summary sheet (opening balance, totals,
 * closing balance) and an Entries sheet with the running balance. Both come from
 * AccountStatementQuery, the query the account page uses, so payroll is masked
 * for anyone without the salary permission exactly as it is on screen.
 */
class AccountStatementExport implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly Account $account,
        private readonly User $viewer,
        private readonly ?string $from,
        private readonly ?string $to,
        private readonly AccountStatementQuery $statement,
    ) {}

    /** @return list<Export> */
    public function sheets(): array
    {
        $summary = $this->statement->summary($this->account, $this->from, $this->to);

        return [
            new AccountStatementSummarySheet($this->account, $this->from, $this->to, $summary),
            new AccountStatementEntriesSheet($this->account, $this->viewer, $this->from, $this->to, $summary['startCents'], $this->statement),
        ];
    }
}
