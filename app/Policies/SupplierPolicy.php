<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/** Suppliers and the costs that go with them are for the owner and finance. */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isFinance();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }
}
