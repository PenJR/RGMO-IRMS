<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(['manage-suppliers', 'view-suppliers', 'manage-inventory']);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(['manage-suppliers', 'manage-inventory']);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(['manage-suppliers', 'manage-inventory']);
    }

    public function deactivate(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission(['manage-suppliers', 'manage-inventory']);
    }
}
