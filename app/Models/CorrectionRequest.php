<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CorrectionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $session_id
 * @property int $employee_id
 * @property CarbonImmutable $proposed_ended_at
 * @property string|null $note
 * @property CorrectionStatus $status
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 */
#[Fillable([
    'session_id', 'employee_id', 'proposed_ended_at', 'note',
    'status', 'decided_by', 'decided_at',
])]
class CorrectionRequest extends Model
{
    /** @use HasFactory<CorrectionRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'employee_id' => 'integer',
            'proposed_ended_at' => 'immutable_datetime',
            'status' => CorrectionStatus::class,
            'decided_by' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @param Builder<static> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CorrectionStatus::Pending);
    }
}
