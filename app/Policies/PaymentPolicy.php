<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $this->canSee($user, $payment);
    }

    /** Only the owner or finance may reverse, and only within their scope. */
    public function reverse(User $user, Payment $payment): bool
    {
        return ($user->isOwner() || $user->isFinance())
            && $this->canSee($user, $payment)
            && ! $payment->isReversed();
    }

    private function canSee(User $user, Payment $payment): bool
    {
        $due = $payment->due;

        if ($due === null) {
            return false;
        }

        if ($due->employee_id !== null && ! $user->can('view-salaries')) {
            return false;
        }

        if ($user->office_id !== null && $due->office_id !== $user->office_id) {
            return false;
        }

        return ! $user->isAccountManager()
            || $due->client?->account_manager_id === $user->id;
    }
}
