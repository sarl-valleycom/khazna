<?php

namespace App\Policies;

use App\Models\User;

class CorrectionRequestPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function decide(User $user): bool
    {
        return $user->isOwner();
    }
}
