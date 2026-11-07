<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Supplier $record): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Supplier $record): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Supplier $record): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}
