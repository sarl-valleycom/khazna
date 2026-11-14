<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money moved between two accounts, in the same office or not. Never income or
 * expense; only its optional fee is.
 *
 * @property int $id
 * @property int $from_account_id
 * @property int $to_account_id
 * @property int $amount_cents
 * @property int $fee_cents
 * @property CarbonImmutable $transfer_date
 * @property string|null $note
 * @property int|null $created_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 */
#[Fillable(['from_account_id', 'to_account_id', 'amount_cents', 'fee_cents', 'transfer_date', 'note', 'created_by', 'reversed_at', 'reversal_reason', 'reversed_by'])]
class Transfer extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'from_account_id' => 'integer',
            'to_account_id' => 'integer',
            'amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'transfer_date' => 'immutable_date',
            'created_by' => 'integer',
            'reversed_at' => 'immutable_datetime',
            'reversed_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /** Between accounts of different offices (FR-ACC-08). Needs both accounts loaded. */
    public function isInterOffice(): bool
    {
        return ($this->from->office_id ?? null) !== ($this->to->office_id ?? null);
    }
}
