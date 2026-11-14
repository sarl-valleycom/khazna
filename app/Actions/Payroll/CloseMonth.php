<?php

namespace App\Actions\Payroll;

use App\Data\PayrollLineData;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Category;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CloseMonth
{
    public function __construct(private readonly PayrollCalculator $calculator) {}

    /**
     * What closing the month would do. Writes nothing (FR-SAL-05). With an actor, only the
     * employees that user may see are included; without one (a console run) everyone is.
     *
     * @return array<int, PayrollLineData>
     */
    public function preview(CarbonImmutable $month, ?User $actor = null): array
    {
        $first = $month->startOfMonth();
        $existing = Due::query()
            ->whereNotNull('employee_id')
            ->whereDate('period', $first->toDateString())
            ->get()
            ->keyBy('employee_id');

        $lines = [];

        foreach ($this->calculator->payableEmployees($first, $actor) as $employee) {
            $line = $this->calculator->forEmployee($employee, $first);
            $due = $existing->get($employee->id);

            if ($line === null) {
                $lines[] = $this->line($employee, [
                    'salary' => 0, 'bonuses' => 0, 'deductions' => 0, 'advances' => 0, 'net' => 0,
                ], 'nothing', null, __('No salary is set for this month.'));

                continue;
            }

            $lines[] = match (true) {
                $due !== null => $this->line($employee, $line, 'exists', $due->id, __('Already closed.')),
                $line['net'] <= 0 => $this->line($employee, $line, 'nothing', null, __('Nothing to pay: advances and deductions cover the salary.')),
                default => $this->line($employee, $line, 'ready', null, null),
            };
        }

        return $lines;
    }

    /**
     * Create one salary due per employee for the month, in one transaction. Safe to
     * run twice: an employee who already has a due for the month is skipped (FR-SAL-04).
     *
     * @return array{created: array<int, PayrollLineData>, existing: array<int, PayrollLineData>, skipped: array<int, PayrollLineData>}
     */
    public function handle(CarbonImmutable $month, ?User $actor = null): array
    {
        $first = $month->startOfMonth();
        $dueDay = max(1, min(28, (int) Setting::current(Setting::SALARY_DUE_DAY, (string) config('khazna.salary_due_day'))));
        $dueOn = $first->addMonthNoOverflow()->day($dueDay)->toDateString();
        $category = Category::named(Category::SALARIES);

        return DB::transaction(function () use ($first, $dueOn, $category, $actor): array {
            $result = ['created' => [], 'existing' => [], 'skipped' => []];

            foreach ($this->preview($first, $actor) as $line) {
                if ($line->state !== 'ready') {
                    $result[$line->state === 'exists' ? 'existing' : 'skipped'][] = $line;

                    continue;
                }

                $employee = Employee::query()->with('office:id,name')->findOrFail($line->employeeId);

                // The unique (employee_id, period) key is the real guard against a double close,
                // even if two people press the button at the same moment.
                try {
                    Due::query()->create([
                        'kind' => DueKind::Salary,
                        'direction' => Direction::Out,
                        'employee_id' => $employee->id,
                        'office_id' => $employee->office_id,
                        'category_id' => $category->id,
                        'period' => $first->toDateString(),
                        'due_on' => $dueOn,
                        'amount_due_cents' => $line->netCents,
                        'amount_paid_cents' => 0,
                        'status' => DueStatus::Pending,
                        'description' => __('Salary :month', ['month' => $first->format('F Y')]),
                    ]);

                    $result['created'][] = $line;
                } catch (UniqueConstraintViolationException) {
                    $result['existing'][] = $line;
                }
            }

            return $result;
        });
    }

    /**
     * @param  array{salary: int, bonuses: int, deductions: int, advances: int, net: int}  $figures
     */
    private function line(Employee $employee, array $figures, string $state, ?int $dueId, ?string $note): PayrollLineData
    {
        return new PayrollLineData(
            employeeId: $employee->id,
            employeeName: $employee->name,
            officeName: $employee->office->name ?? '',
            salaryCents: $figures['salary'],
            bonusCents: $figures['bonuses'],
            deductionCents: $figures['deductions'],
            advanceCents: $figures['advances'],
            netCents: $figures['net'],
            state: $state,
            dueId: $dueId,
            note: $note,
        );
    }

    public static function describe(int $cents): string
    {
        return Money::fromCents($cents)->format();
    }
}
