<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Los cobros los gestiona el admin. Un cobro registrado no se edita ni se borra: si hubo un
 * error se anula (queda en el historial y se revierte su efecto en cuotas y cuenta corriente).
 * El cobro desde la calle queda para la app del vendedor.
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

    public function cancel(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
