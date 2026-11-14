<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place money lives: a cash box, a CCP or bank account. The balance is never
 * stored. It is the opening balance plus the sum of the account's entries.
 *
 * @property int $id
 * @property int $office_id
 * @property string $name
 * @property AccountType $type
 * @property string $currency
 * @property int $opening_balance_cents
 * @property CarbonImmutable $opening_date
 * @property bool $active
 * @property int|null $balance_cents Only present after withBalance().
 */
#[Fillable(['office_id', 'name', 'type', 'currency', 'opening_balance_cents', 'opening_date', 'active'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, HasFactory, HasVisibility;

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'type' => AccountType::class,
            'opening_balance_cents' => 'integer',
            'opening_date' => 'immutable_date',
            'active' => 'boolean',
            'balance_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<AccountEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(AccountEntry::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Add `balance_cents` (opening balance plus the net of all entries) to each
     * row from a single subquery, so a list never needs one query per account.
     *
     * @param  Builder<Account>  $query
     */
    public function scopeWithBalance(Builder $query): void
    {
        $query->select('accounts.*')->selectRaw(
            'accounts.opening_balance_cents + COALESCE((
                SELECT SUM(CASE WHEN account_entries.direction = ? THEN account_entries.amount_cents ELSE -account_entries.amount_cents END)
                FROM account_entries
                WHERE account_entries.account_id = accounts.id
            ), 0) AS balance_cents',
            ['in'],
        );
    }

    public function balanceCents(): int
    {
        return (int) static::query()->withBalance()->whereKey($this->getKey())->value('balance_cents');
    }

    public function hasEntries(): bool
    {
        return $this->entries()->exists();
    }
}
