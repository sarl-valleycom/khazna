<?php

namespace App\Models;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property AttendanceStatus $status
 * @property AttendanceReviewStatus $review_status
 * @property string|null $report
 * @property string|null $blockers
 * @property AttendanceSource $source
 * @property int|null $closed_by
 * @property Collection<int, AttendanceBreak> $breaks
 */
#[Fillable([
    'employee_id', 'started_at', 'ended_at', 'status', 'review_status',
    'report', 'blockers', 'source', 'closed_by',
])]
class AttendanceSession extends Model
{
    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'status' => AttendanceStatus::class,
            'review_status' => AttendanceReviewStatus::class,
            'source' => AttendanceSource::class,
            'closed_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<AttendanceBreak, $this> */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class, 'session_id');
    }

    /** @param Builder<static> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
