<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function setPassword(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isOwner() && ! $user->is($model);
    }
}
