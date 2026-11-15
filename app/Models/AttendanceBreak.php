<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AttendanceBreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $session_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $reason
 * @property bool $paid
 */
#[Fillable(['session_id', 'started_at', 'ended_at', 'reason', 'paid'])]
class AttendanceBreak extends Model
{
    /** @use HasFactory<AttendanceBreakFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'paid' => 'boolean',
        ];
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
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
