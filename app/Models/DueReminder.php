<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reminder sent for a due: who, when and with which template (FR-REM-03).
 *
 * @property int $id
 * @property int $due_id
 * @property int|null $user_id
 * @property string $channel
 * @property string $template
 * @property string $message
 * @property string $phone
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['due_id', 'user_id', 'channel', 'template', 'message', 'phone'])]
class DueReminder extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'due_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Due, $this> */
    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
