<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact
 * @property string $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $notes
 * @property int $account_manager_id
 * @property int|null $office_id
 * @property string|null $crm_lead_id
 */
#[Fillable([
    'name',
    'contact',
    'phone',
    'email',
    'address',
    'notes',
    'account_manager_id',
    'office_id',
    'crm_lead_id',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasFactory, HasVisibility, SoftDeletes;

    protected function casts(): array
    {
        return [
            'account_manager_id' => 'integer',
            'office_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function visibilityOwnerColumn(): ?string
    {
        return 'account_manager_id';
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Clients are soft-deleted and cannot be permanently deleted.'));
    }
}
