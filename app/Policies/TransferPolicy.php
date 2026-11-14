<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

/** For the owner and finance. A restricted user sees a transfer only when both its accounts are in their office. */
class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null
                || (($transfer->from->office_id ?? null) === $user->office_id && ($transfer->to->office_id ?? null) === $user->office_id));
    }

    public function reverse(User $user, Transfer $transfer): bool
    {
        return $this->view($user, $transfer) && ! $transfer->isReversed();
    }
}
