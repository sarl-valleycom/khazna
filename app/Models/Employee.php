<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasVisibility;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * There is deliberately no salary attribute: the salary in force on a date is
 * the latest salary history row on or before it (see salaryOn()). Nothing that
 * lists or serialises employees can therefore carry a salary by accident.
 *
 * @property int $id
 * @property int $office_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $title
 * @property CarbonImmutable|null $hire_date
 * @property EmployeeStatus $status
 * @property CarbonImmutable|null $left_on
 */
#[Fillable(['office_id', 'name', 'phone', 'title', 'hire_date', 'status', 'left_on'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory, HasVisibility, SoftDeletes;

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'hire_date' => 'immutable_date',
            'left_on' => 'immutable_date',
            'status' => EmployeeStatus::class,
        ];
    }

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** @return HasMany<EmployeeSalary, $this> */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /** @return HasMany<SalaryAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /** The monthly salary in force on a date: the latest raise on or before it. Null before the first one. */
    public function salaryOn(CarbonInterface|string $date): ?int
    {
        $value = $this->salaries()
            ->whereDate('effective_from', '<=', $date instanceof CarbonInterface ? $date->toDateString() : $date)
            ->orderByDesc('effective_from')
            ->value('amount_cents');

        return $value === null ? null : (int) $value;
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('Employees are soft-deleted and cannot be permanently deleted.'));
    }
}
