<?php

namespace App\Policies;

use App\Models\Procurement;
use App\Models\User;

class ProcurementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(['view-inventory', 'manage-inventory', 'approve-purchase-orders']);
    }

    public function view(User $user, Procurement $procurement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(['manage-purchase-orders', 'manage-inventory']);
    }

    public function update(User $user, Procurement $procurement): bool
    {
        return $this->create($user) && $procurement->isEditable();
    }

    public function submit(User $user, Procurement $procurement): bool
    {
        return $this->update($user, $procurement) && $procurement->prepared_by === $user->id;
    }

    public function approve(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission('approve-purchase-orders') && $procurement->isPendingApproval();
    }

    public function reject(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission('approve-purchase-orders') && $procurement->isPendingApproval();
    }

    public function cancel(User $user, Procurement $procurement): bool
    {
        return $this->create($user) && $procurement->canCancel() && ($procurement->prepared_by === $user->id || $user->hasPermission('manage-inventory'));
    }

    public function receive(User $user, Procurement $procurement): bool
    {
        return $user->hasPermission('manage-inventory') && $procurement->isReceivable();
    }
}
