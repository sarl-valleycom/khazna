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
        return $user->can('view-salaries') && $this->canSee($user, $employee);
    }

    private function canSee(User $user, Employee $employee): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null || $employee->office_id === $user->office_id);
    }
}
