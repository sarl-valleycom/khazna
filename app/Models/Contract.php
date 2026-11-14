<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
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
 * @property ContractType $type
 * @property Direction $direction
 * @property int|null $client_id
 * @property int|null $supplier_id
 * @property int|null $category_id
 * @property int $office_id
 * @property int $amount_cents
 * @property Cycle $cycle
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property ContractStatus $status
 * @property bool $auto_renew
 * @property string|null $notes
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 */
#[Fillable([
    'type',
    'direction',
    'client_id',
    'supplier_id',
    'category_id',
    'office_id',
    'amount_cents',
    'cycle',
    'starts_on',
    'ends_on',
    'status',
    'auto_renew',
    'notes',
    'attachment_path',
    'attachment_name',
    'attachment_mime',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, HasFactory, HasVisibility, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'direction' => Direction::class,
            'client_id' => 'integer',
            'supplier_id' => 'integer',
            'category_id' => 'integer',
            'office_id' => 'integer',
            'amount_cents' => 'integer',
            'cycle' => Cycle::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => ContractStatus::class,
            'auto_renew' => 'boolean',
        ];
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position');
    }

    /**
     * Eager-load everything a contract row shows, so a list never lazy-loads.
     *
     * @param  Builder<Contract>  $query
     */
    public function scopeWithParties(Builder $query): void
    {
        $query->with([
            'client:id,name,account_manager_id',
            'supplier:id,name',
            'category:id,name',
            'office:id,name',
        ]);
    }

    /** The client's or supplier's name. Needs the relations loaded. */
    public function partyName(): string
    {
        return $this->client->name ?? $this->supplier->name ?? '';
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /**
     * Account managers see the contracts of their own clients; office-restricted
     * users see only contracts attributed to their office.
     *
     * @param  Builder<Contract>  $query
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

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Contracts are soft-deleted and cannot be permanently deleted.'));
    }
}
