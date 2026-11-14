<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "On this date the real balance was X." Recorded as history; any difference
 * becomes a separate adjustment entry in the ledger, never an edit.
 *
 * @property int $id
 * @property int $account_id
 * @property CarbonImmutable $as_of
 * @property int $counted_balance_cents
 * @property int $system_balance_cents
 * @property int $difference_cents
 * @property string|null $note
 * @property int|null $adjustment_entry_id
 * @property int|null $created_by
 */
#[Fillable(['account_id', 'as_of', 'counted_balance_cents', 'system_balance_cents', 'difference_cents', 'note', 'adjustment_entry_id', 'created_by'])]
class Reconciliation extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'as_of' => 'immutable_date',
            'counted_balance_cents' => 'integer',
            'system_balance_cents' => 'integer',
            'difference_cents' => 'integer',
            'adjustment_entry_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<AccountEntry, $this> */
    public function adjustmentEntry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'adjustment_entry_id');
    }
}
