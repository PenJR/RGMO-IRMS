<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function viewAnyCorrections(User $user): bool
    {
        return $user->isActive() && ! $user->isAccountLocked()
            && in_array('view-delivery-corrections', config('rbac.roles.'.$user->normalizedRole().'.permissions', []), true);
    }

    public function viewCorrections(User $user, Delivery $delivery): bool
    {
        return $this->viewAnyCorrections($user);
    }

    public function correct(User $user, Delivery $delivery): bool
    {
        // Administrators normally bypass named permissions in User::hasPermission.
        // Check this dedicated grant explicitly so correction access can be revoked.
        return $user->isAdmin() && $this->viewCorrections($user, $delivery)
            && in_array('correct-deliveries', config('rbac.roles.admin.permissions', []), true);
    }
}
