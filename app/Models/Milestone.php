<?php

namespace App\Models;

use App\Enums\MilestoneTrigger;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $contract_id
 * @property int $position
 * @property string $label
 * @property int $amount_cents
 * @property CarbonImmutable|null $due_on
 * @property MilestoneTrigger $trigger
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $requested_at
 */
#[Fillable(['contract_id', 'position', 'label', 'amount_cents', 'due_on', 'trigger', 'delivered_at', 'requested_at'])]
class Milestone extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'contract_id' => 'integer',
            'position' => 'integer',
            'amount_cents' => 'integer',
            'due_on' => 'immutable_date',
            'trigger' => MilestoneTrigger::class,
            'delivered_at' => 'immutable_date',
            'requested_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasOne<Due, $this> */
    public function due(): HasOne
    {
        return $this->hasOne(Due::class);
    }
}
