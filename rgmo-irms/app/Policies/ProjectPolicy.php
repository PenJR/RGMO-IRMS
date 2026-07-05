<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Handle view any.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-projects');
    }

    /**
     * Handle view.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('view-projects');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage-projects');
    }

    /**
     * Update the specified resource.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('manage-projects');
    }

    /**
     * Handle delete.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('manage-projects');
    }
}
