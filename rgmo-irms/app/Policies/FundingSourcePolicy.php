<?php

namespace App\Policies;

use App\Models\FundingSource;
use App\Models\User;

class FundingSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-funding-sources');
    }

    public function view(User $user, FundingSource $fundingSource): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FundingSource $fundingSource): bool
    {
        return $this->viewAny($user);
    }

    public function deactivate(User $user, FundingSource $fundingSource): bool
    {
        return $this->viewAny($user);
    }

    public function reactivate(User $user, FundingSource $fundingSource): bool
    {
        return $this->viewAny($user);
    }
}
