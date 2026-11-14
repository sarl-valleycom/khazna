<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $month First day of the month the adjustment counts toward.
 * @property AdjustmentType $type
 * @property int $amount_cents
 * @property string $reason
 * @property CarbonImmutable|null $paid_on
 * @property PaymentMethod|null $method
 * @property int|null $account_id
 * @property int|null $created_by
 */
#[Fillable(['employee_id', 'month', 'type', 'amount_cents', 'reason', 'paid_on', 'method', 'account_id', 'created_by'])]
class SalaryAdjustment extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'month' => 'immutable_date',
            'type' => AdjustmentType::class,
            'amount_cents' => 'integer',
            'paid_on' => 'immutable_date',
            'method' => PaymentMethod::class,
            'account_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
