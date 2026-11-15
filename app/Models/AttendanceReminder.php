<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property string $type
 */
#[Fillable(['employee_id', 'date', 'type'])]
class AttendanceReminder extends Model
{
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'date' => 'immutable_date',
        ];
    }
}
