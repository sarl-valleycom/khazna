<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Owner and finance manage the people. What they earn is a separate permission
 * (the `view-salaries` gate), checked on top of this.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->canSee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->canSee($user, $employee);
    }

    /** Changing what someone earns needs the salary permission as well as access to the employee. */
    public function setSalary(User $user, Employee $employee): bool
    {
        return $this->salaryAccess($user, $employee);
    }

    /**
     * Reading what someone earns (their payslip). Today it needs the same two
     * things as changing it. It is its own ability so that either rule can be
     * tightened later without silently changing the other.
     */
    public function viewSalary(User $user, Employee $employee): bool
    {
        return $this->salaryAccess($user, $employee);
    }

    private function salaryAccess(User $user, Employee $employee): bool
    {
        return $user->can('view-salaries') && $this->canSee($user, $employee);
    }

    private function canSee(User $user, Employee $employee): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null || $employee->office_id === $user->office_id);
    }
}
