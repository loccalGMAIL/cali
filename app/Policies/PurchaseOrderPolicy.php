<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Las órdenes de compra solo las gestiona el admin. Se editan mientras están abiertas
 * (borrador o emitida); no se borran: si hubo un error se anulan (queda en el historial).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() && $purchaseOrder->isOpen();
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->isAdmin() && $purchaseOrder->isOpen();
    }
}
