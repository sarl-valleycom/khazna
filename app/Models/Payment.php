<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $id
 * @property int $due_id
 * @property int $account_id
 * @property int $amount_cents
 * @property CarbonImmutable $paid_on
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $receipt_path
 * @property string|null $receipt_mime
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $reversed_by
 */
#[Fillable([
    'due_id',
    'account_id',
    'amount_cents',
    'paid_on',
    'method',
    'reference',
    'receipt_path',
    'receipt_mime',
    'notes',
    'recorded_by',
    'reversed_at',
    'reversal_reason',
    'reversed_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'due_id' => 'integer',
            'account_id' => 'integer',
            'amount_cents' => 'integer',
            'paid_on' => 'immutable_date',
            'method' => PaymentMethod::class,
            'recorded_by' => 'integer',
            'reversed_at' => 'immutable_datetime',
            'reversed_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Due, $this> */
    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * A payment is visible to whoever can see its due.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('due', fn (Builder $due): Builder => $due->visibleTo($user));
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeNotReversed(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('reversed_at'));
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Payments are never permanently deleted. Reverse the payment instead.'));
    }
}
