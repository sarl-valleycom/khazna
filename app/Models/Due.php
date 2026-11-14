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
 * @property int|null $client_id
 * @property int|null $supplier_id
 * @property int|null $employee_id
 * @property int|null $milestone_id
 * @property int|null $category_id
 * @property int $office_id
 * @property CarbonImmutable|null $period
 * @property CarbonImmutable|null $due_on Null only for a milestone still waiting for delivery.
 * @property int $amount_due_cents
 * @property int $amount_paid_cents
 * @property DueStatus $status
 * @property string|null $description
 * @property string|null $canceled_reason
 * @property bool $canceled_by_contract
 */
#[Fillable([
    'kind',
    'direction',
    'contract_id',
    'client_id',
    'supplier_id',
    'employee_id',
    'milestone_id',
    'category_id',
    'office_id',
    'period',
    'due_on',
    'amount_due_cents',
    'amount_paid_cents',
    'status',
    'description',
    'canceled_reason',
    'canceled_by_contract',
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
            'supplier_id' => 'integer',
            'employee_id' => 'integer',
            'milestone_id' => 'integer',
            'category_id' => 'integer',
            'office_id' => 'integer',
            'period' => 'immutable_date',
            'due_on' => 'immutable_date',
            'amount_due_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'status' => DueStatus::class,
            'canceled_by_contract' => 'boolean',
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

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
        // Salary dues exist only for people who may see salaries. Everyone else gets
        // them filtered out at the query, so they cannot appear in any list or total.
        if (! $user->can('view-salaries')) {
            $query->whereNull($this->qualifyColumn('employee_id'));
        }

        if ($user->office_id !== null) {
            $query->where($this->qualifyColumn('office_id'), $user->office_id);
        }

        if ($user->role === Role::AccountManager) {
            $query->whereHas('client', fn (Builder $query): Builder => $query->where('account_manager_id', $user->id));
        }
    }

    /**
     * Eager-load everything a due row shows, so a list never lazy-loads a party.
     *
     * @param  Builder<Due>  $query
     */
    public function scopeWithParties(Builder $query): void
    {
        $query->with([
            'client:id,name,account_manager_id',
            'supplier:id,name',
            'employee:id,name',
            'office:id,name',
        ]);
    }

    /** The client, supplier or employee this due is with. Needs the relations loaded (see withParties). */
    public function partyName(): string
    {
        return $this->client->name ?? $this->supplier->name ?? $this->employee->name ?? '';
    }

    public function partyType(): string
    {
        return match (true) {
            $this->client_id !== null => 'client',
            $this->supplier_id !== null => 'supplier',
            $this->employee_id !== null => 'employee',
            default => 'none',
        };
    }

    /** True for a milestone still waiting for delivery: it has no date, so no time-based tab holds it. */
    public function isWaitingForDelivery(): bool
    {
        return $this->due_on === null;
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_on !== null
            && $this->due_on->lt(AlgerianDate::today());
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
