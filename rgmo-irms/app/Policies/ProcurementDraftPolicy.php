<?php

namespace App\Policies;

use App\Models\ProcurementDraft;
use App\Models\User;

class ProcurementDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && ! $user->isAccountLocked() && $user->hasPermission('manage-inventory');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ProcurementDraft $draft): bool
    {
        return $this->viewAny($user) && $draft->user_id === $user->id;
    }

    public function update(User $user, ProcurementDraft $draft): bool
    {
        return $this->view($user, $draft) && $draft->isActive();
    }

    public function delete(User $user, ProcurementDraft $draft): bool
    {
        return $this->update($user, $draft);
    }
}
