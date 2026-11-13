<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

abstract class BasePolicy
{
    /**
     * The owner may do everything. Other roles fall through to the policy
     * method, which decides on the resource itself.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === Role::Owner ? true : null;
    }
}
