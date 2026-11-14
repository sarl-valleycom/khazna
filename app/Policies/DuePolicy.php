<?php

namespace App\Policies;

use App\Models\Due;
use App\Models\User;

class DuePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Due $due): bool
    {
        return $this->canSee($user, $due);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function postpone(User $user, Due $due): bool
    {
        return $this->canSee($user, $due) && $due->status->isOpen();
    }

    /** Every role may record a payment on a due it can see. */
    public function pay(User $user, Due $due): bool
    {
        return $this->canSee($user, $due) && $due->status->isOpen();
    }

    /** Preparing a WhatsApp reminder, for any open due the user can see. */
    public function remind(User $user, Due $due): bool
    {
        return $this->canSee($user, $due) && $due->status->isOpen();
    }

    public function cancel(User $user, Due $due): bool
    {
        return $this->canSee($user, $due) && $due->status->isOpen();
    }

    private function canSee(User $user, Due $due): bool
    {
        if ($user->office_id !== null && $due->office_id !== $user->office_id) {
            return false;
        }

        return ! $user->isAccountManager()
            || $due->client?->account_manager_id === $user->id;
    }
}
