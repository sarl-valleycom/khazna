<?php

namespace App\Models;

use App\Enums\Direction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One movement of money on one account. Append only: a mistake is corrected
 * with an opposite entry, never by editing or deleting. Only LedgerWriter
 * should create these.
 *
 * @property int $id
 * @property int $account_id
 * @property Direction $direction
 * @property int $amount_cents
 * @property CarbonImmutable $entry_date
 * @property string $source_type
 * @property int $source_id
 * @property int|null $category_id
 * @property bool $is_payroll
 * @property string|null $description
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property int|null $running_cents Only present on statement queries.
 */
#[Fillable(['account_id', 'direction', 'amount_cents', 'entry_date', 'source_type', 'source_id', 'category_id', 'is_payroll', 'description', 'created_by'])]
class AccountEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Ledger entries are append-only; they cannot be updated. Add an opposite entry instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Ledger entries are append-only; they cannot be deleted. Add an opposite entry instead.');
        });
    }

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'direction' => Direction::class,
            'amount_cents' => 'integer',
            'entry_date' => 'immutable_date',
            'source_id' => 'integer',
            'category_id' => 'integer',
            'is_payroll' => 'boolean',
            'created_by' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Positive for money in, negative for money out. */
    public function signedCents(): int
    {
        return $this->direction === Direction::In ? $this->amount_cents : -$this->amount_cents;
    }
}
