<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Los cobros los gestiona el admin y, una vez registrados, no se editan ni se borran
 * (ya impactaron cuotas y cuenta corriente). El cobro desde la calle queda para la app del vendedor.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
