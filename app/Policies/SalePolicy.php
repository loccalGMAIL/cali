<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/**
 * El admin gestiona todas las ventas; el vendedor crea ventas y ve solo las suyas.
 * Una venta confirmada no se edita ni se borra: si hubo un error se anula (queda en el historial).
 */
class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->isAdmin() || $sale->seller_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Anular una venta revierte cuotas, cuenta corriente y pendientes de compra: solo el admin.
     */
    public function cancel(User $user, Sale $sale): bool
    {
        return $user->isAdmin();
    }
}
