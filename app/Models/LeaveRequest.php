<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $employee_id
 * @property LeaveType $type
 * @property CarbonImmutable $from
 * @property CarbonImmutable $to
 * @property string|null $reason
 * @property LeaveStatus $status
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 */
#[Fillable([
    'employee_id', 'type', 'from', 'to', 'reason', 'status', 'decided_by', 'decided_at',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'type' => LeaveType::class,
            'from' => 'immutable_date',
            'to' => 'immutable_date',
            'status' => LeaveStatus::class,
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function coversOn(CarbonInterface|string $date): bool
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $this->from->toDateString() <= $day && $this->to->toDateString() >= $day;
    }
}
