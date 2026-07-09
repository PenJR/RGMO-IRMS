<?php

namespace App\Policies;

use App\Models\ResourceRequest;
use App\Models\User;

class ResourceRequestPolicy
{
    /**
     * Determine if the user can view all requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('review-request') || $user->hasPermission('approve-request');
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, ResourceRequest $request): bool
    {
        return $user->id === $request->user_id || $user->hasPermission('review-request') || $user->hasPermission('approve-request');
    }

    /**
     * Determine if the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('submit-request');
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, ResourceRequest $request): bool
    {
        if ($user->hasPermission('manage-users')) {
            return true;
        }

        if ($user->id === $request->user_id && $request->isPending() && $user->hasPermission('update-pending-request')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, ResourceRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        return $user->hasPermission('manage-users')
            || ($user->id === $request->user_id && $user->hasPermission('update-pending-request'));
    }

    /**
     * Determine if the user can approve the model.
     */
    public function approve(User $user, ResourceRequest $request): bool
    {
        return $user->hasPermission('approve-request') && $request->isPending();
    }

    /**
     * Determine if the user can reject the model.
     */
    public function reject(User $user, ResourceRequest $request): bool
    {
        return $user->hasPermission('approve-request') && $request->isPending();
    }

    /**
     * Determine if the user can review resource requests.
     */
    public function review(User $user): bool
    {
        return $user->hasPermission('review-request');
    }

    /**
     * Determine if the user can fulfill an approved request.
     */
    public function fulfill(User $user, ResourceRequest $request): bool
    {
        return $user->hasPermission('record-withdrawal') && $request->isApproved();
    }
}
