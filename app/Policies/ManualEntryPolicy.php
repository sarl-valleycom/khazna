<?php

namespace App\Policies;

use App\Models\ManualEntry;
use App\Models\User;

class ManualEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ManualEntry $entry): bool
    {
        return $this->viewAny($user)
            && ($user->office_id === null || ($entry->account->office_id ?? null) === $user->office_id);
    }

    public function reverse(User $user, ManualEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $entry->isReversed();
    }
}
