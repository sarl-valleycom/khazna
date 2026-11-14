<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

/**
 * Balances and statements are for the owner and finance. Account managers
 * never see them, even though they may pick an account when recording a payment.
 */
class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function view(User $user, Account $account): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null || $account->office_id === $user->office_id);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Account $account): bool
    {
        return $user->isOwner();
    }
}
