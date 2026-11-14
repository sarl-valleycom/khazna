<?php

namespace App\Actions\Ledger;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The only code that inserts ledger entries. Callers run it inside their own
 * transaction so the entry and the thing that caused it commit together.
 */
class LedgerWriter
{
    /**
     * @param  string  $dateField  The form field to blame when the date is refused.
     * @param  bool  $isPayroll  Salary payments and advances: hidden from anyone without the salary permission.
     */
    public function record(
        Account $account,
        Direction $direction,
        int $amountCents,
        CarbonImmutable $entryDate,
        Model $source,
        ?User $by,
        ?string $description = null,
        string $dateField = 'entry_date',
        ?int $categoryId = null,
        bool $isPayroll = false,
    ): AccountEntry {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('A ledger entry needs a positive amount.');
        }

        if ($entryDate->startOfDay()->lt($account->opening_date->startOfDay())) {
            throw ValidationException::withMessages([
                $dateField => __('This account starts on :date. Choose that date or a later one.', [
                    'date' => $account->opening_date->format('d M Y'),
                ]),
            ]);
        }

        return AccountEntry::query()->create([
            'account_id' => $account->id,
            'direction' => $direction,
            'amount_cents' => $amountCents,
            'entry_date' => $entryDate->toDateString(),
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'description' => $description,
            'category_id' => $categoryId,
            'is_payroll' => $isPayroll,
            'created_by' => $by?->id,
        ]);
    }
}
