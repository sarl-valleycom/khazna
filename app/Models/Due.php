<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Database\Factories\DueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $id
 * @property DueKind $kind
 * @property Direction $direction
 * @property int|null $contract_id
 * @property int $client_id
 * @property int $office_id
 * @property CarbonImmutable $period
 * @property CarbonImmutable $due_on
 * @property int $amount_due_cents
 * @property int $amount_paid_cents
 * @property DueStatus $status
 * @property string|null $description
 * @property string|null $canceled_reason
 */
#[Fillable([
    'kind',
    'direction',
    'contract_id',
    'client_id',
    'office_id',
    'period',
    'due_on',
    'amount_due_cents',
    'amount_paid_cents',
    'status',
    'description',
    'canceled_reason',
])]
class Due extends Model
{
    /** @use HasFactory<DueFactory> */
    use Auditable, HasFactory, HasVisibility, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => DueKind::class,
            'direction' => Direction::class,
            'contract_id' => 'integer',
            'client_id' => 'integer',
            'office_id' => 'integer',
            'period' => 'immutable_date',
            'due_on' => 'immutable_date',
            'amount_due_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'status' => DueStatus::class,
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<DuePostponement, $this> */
    public function postponements(): HasMany
    {
        return $this->hasMany(DuePostponement::class);
    }

    /**
     * @param  Builder<Due>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->office_id !== null) {
            $query->where($this->qualifyColumn('office_id'), $user->office_id);
        }

        if ($user->role === Role::AccountManager) {
            $query->whereHas('client', fn (Builder $query): Builder => $query->where('account_manager_id', $user->id));
        }
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_on->lt(AlgerianDate::today());
    }

    public function remainingCents(): int
    {
        return max(0, $this->amount_due_cents - $this->amount_paid_cents);
    }

    /** An overpayment, shown as a credit on the due and never applied elsewhere. */
    public function creditCents(): int
    {
        return max(0, $this->amount_paid_cents - $this->amount_due_cents);
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Dues are soft-deleted and cannot be permanently deleted.'));
    }
}
