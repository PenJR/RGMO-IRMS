<?php

namespace App\Policies;

use App\Models\User;
use App\Models\YearEndInventorySnapshot;

class YearEndInventorySnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && ! $user->isAccountLocked()
            && in_array('generate-reports', config('rbac.roles.'.$user->normalizedRole().'.permissions', []), true);
    }

    public function view(User $user, YearEndInventorySnapshot $snapshot): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && ! $user->isAccountLocked() && $user->isAdmin()
            && in_array('close-inventory-year', config('rbac.roles.admin.permissions', []), true);
    }

    public function update(User $user, YearEndInventorySnapshot $snapshot): bool
    {
        return false;
    }

    public function delete(User $user, YearEndInventorySnapshot $snapshot): bool
    {
        return false;
    }
}
