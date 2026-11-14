<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raise: "this much a month, from this date". Rows are added, never edited,
 * so a raise can never rewrite a month that was already closed.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $amount_cents
 * @property CarbonImmutable $effective_from
 * @property int|null $created_by
 */
#[Fillable(['employee_id', 'amount_cents', 'effective_from', 'created_by'])]
class EmployeeSalary extends Model
{
    use Auditable;

    protected $table = 'employee_salary_history';

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'amount_cents' => 'integer',
            'effective_from' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
