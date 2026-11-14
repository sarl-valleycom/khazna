<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $due_id
 * @property CarbonImmutable $old_due_on
 * @property CarbonImmutable $new_due_on
 * @property string $reason
 * @property int $user_id
 */
#[Fillable(['due_id', 'old_due_on', 'new_due_on', 'reason', 'user_id'])]
class DuePostponement extends Model
{
    protected function casts(): array
    {
        return [
            'due_id' => 'integer',
            'old_due_on' => 'immutable_date',
            'new_due_on' => 'immutable_date',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Due, $this> */
    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
