<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function view(User $user, Customer $record): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function update(User $user, Customer $record): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    public function delete(User $user, Customer $record): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}
