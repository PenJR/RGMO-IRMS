<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ResourceRequest;

class ResourceRequestPolicy
{
    /**
     * Determine if the user can view all requests.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, ResourceRequest $request): bool
    {
        return $user->id === $request->user_id || in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff', 'field_personnel']);
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, ResourceRequest $request): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        // Staff can only update pending requests they created
        if ($user->role === 'staff' && $user->id === $request->user_id && $request->isPending()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, ResourceRequest $request): bool
    {
        return $user->role === 'admin' && $request->isPending();
    }

    /**
     * Determine if the user can approve the model.
     */
    public function approve(User $user, ResourceRequest $request): bool
    {
        return $user->role === 'admin' && $request->isPending();
    }

    /**
     * Determine if the user can reject the model.
     */
    public function reject(User $user, ResourceRequest $request): bool
    {
        return $user->role === 'admin' && $request->isPending();
    }
}
