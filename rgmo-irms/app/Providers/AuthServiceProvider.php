<?php

namespace App\Providers;

use App\Models\FundingSource;
use App\Models\GeneratedReportSnapshot;
use App\Models\IncidentReport;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\ProcurementDraft;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Policies\FundingSourcePolicy;
use App\Policies\GeneratedReportSnapshotPolicy;
use App\Policies\IncidentReportPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\ProcurementDraftPolicy;
use App\Policies\ProcurementPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ResourceRequestPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        InventoryItem::class => InventoryItemPolicy::class,
        IncidentReport::class => IncidentReportPolicy::class,
        FundingSource::class => FundingSourcePolicy::class,
        GeneratedReportSnapshot::class => GeneratedReportSnapshotPolicy::class,
        ResourceRequest::class => ResourceRequestPolicy::class,
        Procurement::class => ProcurementPolicy::class,
        ProcurementDraft::class => ProcurementDraftPolicy::class,
        Project::class => ProjectPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Administrators receive their permissions through config/rbac.php.
        // Do not globally bypass policies because policies also enforce
        // invariants such as preventing self-deletion and invalid transitions.
    }
}
