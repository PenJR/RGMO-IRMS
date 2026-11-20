<?php

namespace App\Policies;

use App\Models\GeneratedReportSnapshot;
use App\Models\User;

class GeneratedReportSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return GeneratedReportSnapshot::reportTypesFor($user) !== [];
    }

    public function view(User $user, GeneratedReportSnapshot $snapshot): bool
    {
        return array_key_exists($snapshot->report_key, GeneratedReportSnapshot::reportTypesFor($user));
    }

    public function create(User $user, ?string $reportKey = null): bool
    {
        return $reportKey === null
            ? $this->viewAny($user)
            : array_key_exists($reportKey, GeneratedReportSnapshot::reportTypesFor($user));
    }

    public function update(User $user, GeneratedReportSnapshot $snapshot): bool
    {
        return false;
    }

    public function delete(User $user, GeneratedReportSnapshot $snapshot): bool
    {
        return false;
    }
}
