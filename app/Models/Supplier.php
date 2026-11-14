<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Someone we pay: a hosting company, a landlord, a freelancer.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 */
#[Fillable(['name', 'contact', 'phone', 'email', 'notes'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, HasFactory, SoftDeletes;

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

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Suppliers are soft-deleted and cannot be permanently deleted.'));
    }
}
