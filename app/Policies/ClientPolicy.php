<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->canSee($user, $client);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->canSee($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->canSee($user, $client);
    }

    public function import(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    private function canSee(User $user, Client $client): bool
    {
        if ($user->office_id !== null && $client->office_id !== $user->office_id) {
            return false;
        }

        return ! $user->isAccountManager()
            || $client->account_manager_id === $user->id;
    }
}
