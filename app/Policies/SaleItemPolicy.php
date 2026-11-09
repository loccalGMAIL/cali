<?php

namespace App\Policies;

use App\Models\SaleItem;
use App\Models\User;

/**
 * Los ítems vendidos se crean con la venta y no se tocan sueltos. Solo el admin ve la lista
 * de pendientes de compra (incluye costos).
 */
class SaleItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SaleItem $saleItem): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SaleItem $saleItem): bool
    {
        return false;
    }

    public function delete(User $user, SaleItem $saleItem): bool
    {
        return false;
    }
}
