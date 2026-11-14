<?php

namespace App\Policies;

use App\Models\Reconciliation;
use App\Models\User;

class ReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function adjust(User $user, Reconciliation $reconciliation): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null || ($reconciliation->account->office_id ?? null) === $user->office_id);
    }
}
