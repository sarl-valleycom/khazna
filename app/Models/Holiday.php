<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property CarbonImmutable $date
 * @property string $label
 */
#[Fillable(['date', 'label'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }
}
