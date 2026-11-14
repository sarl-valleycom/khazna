<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $this->canSee($user, $contract);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->canSee($user, $contract);
    }

    /** Delivering, requesting and rebalancing milestones: anyone who can see the project. */
    public function manageMilestones(User $user, Contract $contract): bool
    {
        return $this->canSee($user, $contract);
    }

    public function changeStatus(User $user, Contract $contract): bool
    {
        return $this->canSee($user, $contract);
    }

    private function canSee(User $user, Contract $contract): bool
    {
        if ($user->office_id !== null && $contract->office_id !== $user->office_id) {
            return false;
        }

        return ! $user->isAccountManager()
            || $contract->client?->account_manager_id === $user->id;
    }
}
