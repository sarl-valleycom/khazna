<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $city
 * @property bool $active
 */
#[Fillable(['name', 'city', 'active'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @param Builder<static> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->office_id !== null) {
            $query->whereKey($user->office_id);
        }
    }
}
