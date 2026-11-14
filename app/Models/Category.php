<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Models\Concerns\Auditable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property CategoryKind $kind
 * @property bool $active
 */
#[Fillable(['name', 'kind', 'active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, HasFactory;

    public const BANK_FEES = 'Bank fees';

    public const RECONCILIATION = 'Reconciliation difference';

    public const SALARIES = 'Salaries';

    /** @return list<string> Categories the system posts to by name: they cannot be renamed or switched off. */
    public static function systemNames(): array
    {
        return [self::BANK_FEES, self::RECONCILIATION, self::SALARIES];
    }

    public function isSystem(): bool
    {
        return $this->kind === CategoryKind::Cost && in_array($this->name, self::systemNames(), true);
    }

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'active' => 'boolean',
        ];
    }

    /** Look up one of the categories the system itself posts to. */
    public static function named(string $name): self
    {
        return static::query()->where('name', $name)->where('kind', CategoryKind::Cost)->firstOrFail();
    }
}
