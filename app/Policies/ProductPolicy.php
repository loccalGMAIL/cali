<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function view(User $user, Product $record): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $record): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Product $record): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}
