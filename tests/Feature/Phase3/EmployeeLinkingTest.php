<?php

use App\Enums\EmploymentType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function attendanceEmployeePayload(Office $office, array $overrides = []): array
{
    return [
        'name' => 'Karim Benali',
        'phone' => '0550 12 34 56',
        'title' => 'Developer',
        'hire_date' => '2025-01-10',
        'office_id' => $office->id,
        'discord_user_id' => '9007199254740993',
        'contract_type' => EmploymentType::FixedMonthly->value,
        'expected_minutes_per_day' => 480,
        'work_days' => [0, 1, 2, 3, 4],
        'schedule_start' => '09:00',
        'schedule_end' => '17:00',
        'late_grace_minutes' => 15,
        'reminders_enabled' => true,
        ...$overrides,
    ];
}

test('a Discord ID above 2^53 is stored and returned as the same string', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('employees.store'), attendanceEmployeePayload($office))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();

    expect($employee->discord_user_id)->toBeString()
        ->and($employee->discord_user_id)->toBe('9007199254740993')
        ->and($employee->contract_type)->toBe(EmploymentType::FixedMonthly)
        ->and($employee->schedule_start)->toBe('09:00:00')
        ->and($employee->workDays())->toBe([0, 1, 2, 3, 4]);

    $this->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->where('employee.employee.discordUserId', '9007199254740993')
        ->where('employee.employee.scheduleStart', '09:00:00')
        ->where('employee.employee.contractType', 'fixed_monthly'));
});

test('one Discord account cannot be linked to two employees', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('employees.store'), attendanceEmployeePayload($office))
        ->assertSessionHasNoErrors();

    $this->post(route('employees.store'), attendanceEmployeePayload($office, ['name' => 'Someone else']))
        ->assertSessionHasErrors('discord_user_id');

    expect(Employee::query()->count())->toBe(1);
});

test('a Discord ID must be numeric and a schedule needs both ends', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('employees.store'), attendanceEmployeePayload($office, ['discord_user_id' => 'karim#1234']))
        ->assertSessionHasErrors('discord_user_id');

    $this->post(route('employees.store'), attendanceEmployeePayload($office, ['schedule_end' => null]))
        ->assertSessionHasErrors('schedule_end');

    $this->post(route('employees.store'), attendanceEmployeePayload($office, ['work_days' => [0, 7]]))
        ->assertSessionHasErrors('work_days.1');
});

test('finance can link employees but never sees or sends an hourly rate', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Finance);

    $this->post(route('employees.store'), attendanceEmployeePayload($office, ['hourly_rate' => 80_000]))
        ->assertSessionHasErrors('hourly_rate');

    $this->post(route('employees.store'), attendanceEmployeePayload($office))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();

    $this->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->missing('employee.hourlyRateCents')
        ->where('canSeeSalaries', false));

    $content = $this->get(route('employees.show', $employee))->getContent();
    expect($content)->not->toContain('hourlyRate');
});

test('an hourly rate is stored as integer centimes for the owner', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('employees.store'), attendanceEmployeePayload($office, [
        'contract_type' => EmploymentType::Hourly->value,
        'hourly_rate' => 80_000,
    ]))->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();

    expect($employee->contract_type)->toBe(EmploymentType::Hourly)
        ->and($employee->hourly_rate)->toBe(80_000);

    $this->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->where('employee.hourlyRateCents', 80_000)
        ->where('employee.employee.contractType', 'hourly'));
});

test('an employee with no work days follows the default Sunday to Thursday week', function () {
    $employee = Employee::factory()->make(['work_days' => null]);

    expect($employee->workDays())->toBe([0, 1, 2, 3, 4])
        ->and($employee->worksOn('2026-11-15'))->toBeTrue()   // Sunday
        ->and($employee->worksOn('2026-11-19'))->toBeTrue()   // Thursday
        ->and($employee->worksOn('2026-11-20'))->toBeFalse()  // Friday
        ->and($employee->worksOn('2026-11-21'))->toBeFalse(); // Saturday

    $weekendWorker = Employee::factory()->make(['work_days' => [5, 6]]);

    expect($weekendWorker->worksOn('2026-11-20'))->toBeTrue()
        ->and($weekendWorker->worksOn('2026-11-15'))->toBeFalse();
});
