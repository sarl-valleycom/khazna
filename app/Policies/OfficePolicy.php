<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

class OfficePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Office $office): bool
    {
        return $user->isOwner();
    }
}
