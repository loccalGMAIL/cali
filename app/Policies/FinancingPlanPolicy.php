<?php

namespace App\Policies;

use App\Models\FinancingPlan;
use App\Models\User;

class FinancingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, FinancingPlan $record): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, FinancingPlan $record): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, FinancingPlan $record): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}
