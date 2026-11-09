<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/**
 * Las compras solo las gestiona el admin y, una vez cargadas, no se editan ni se borran
 * (la orden y el costo ya fueron impactados): si hubo un error se anulan (queda en el historial).
 */
class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin();
    }
}
