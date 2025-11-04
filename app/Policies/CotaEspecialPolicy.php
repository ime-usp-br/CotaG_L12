<?php

namespace App\Policies;

use App\Models\CotaEspecial;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CotaEspecialPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CotaEspecial $cota): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CotaEspecial $cota): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CotaEspecial $cota): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CotaEspecial $cota): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CotaEspecial $cota): bool
    {
        return $user->hasAnyRole('Admin', 'Operador');
    }

    /**
     * Determine whether the user can delete multiple models at once.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Admin', 'Operador');
    }
}
