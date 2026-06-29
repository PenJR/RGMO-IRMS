<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-projects');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('view-projects');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-projects');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('manage-projects');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('manage-projects');
    }
}
