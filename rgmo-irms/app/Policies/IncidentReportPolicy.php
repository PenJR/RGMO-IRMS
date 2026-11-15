<?php

namespace App\Policies;

use App\Models\IncidentReport;
use App\Models\User;

class IncidentReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(['report-incidents', 'view-incidents', 'investigate-incidents', 'resolve-incidents']);
    }

    public function view(User $user, IncidentReport $incident): bool
    {
        return $user->id === $incident->reported_by
            || $user->hasPermission(['view-incidents', 'investigate-incidents', 'resolve-incidents']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('report-incidents');
    }

    public function investigate(User $user, IncidentReport $incident): bool
    {
        return $user->hasPermission('investigate-incidents')
            && $incident->reported_by !== $user->id
            && in_array($incident->status, [IncidentReport::STATUS_SUBMITTED, IncidentReport::STATUS_INVESTIGATING], true);
    }

    public function resolve(User $user, IncidentReport $incident): bool
    {
        return $user->isAdmin()
            && $user->status === User::STATUS_ACTIVE
            && $incident->status === IncidentReport::STATUS_INVESTIGATING
            && $incident->investigated_by !== null
            && $incident->reported_by !== $user->id
            && $incident->investigated_by !== $user->id;
    }
}
