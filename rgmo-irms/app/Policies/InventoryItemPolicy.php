<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InventoryItem;

class InventoryItemPolicy
{
    /**
     * Determine whether the user can view any inventory items.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-inventory');
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('view-inventory');
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    /**
     * Determine if the user can restore the model.
     */
    public function restore(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    /**
     * Determine if the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission('manage-inventory');
    }

    /**
     * Determine if user can export items
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('view-inventory') || $user->hasPermission('generate-reports');
    }

    /**
     * Determine if user can import items
     */
    public function import(User $user): bool
    {
        return $user->hasPermission('manage-inventory');
    }
}
