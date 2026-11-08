<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * El registro de actividad es solo para el admin y es inmutable: nadie lo crea, edita ni borra
 * a mano; lo escribe el sistema.
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }
}
