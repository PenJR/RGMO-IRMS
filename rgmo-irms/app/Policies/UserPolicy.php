<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view all users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-users');
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermission('manage-users');
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage-users');
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermission('manage-users');
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine if user can assign roles
     */
    public function assignRole(User $user): bool
    {
        return $user->hasPermission('assign-roles');
    }

    /**
     * Determine if user can impersonate other users
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->hasPermission('manage-users') && $user->id !== $model->id;
    }
}
