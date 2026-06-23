<?php

namespace App\Providers;

use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\ResourceRequestPolicy;
use Illuminate\Support\Facades\Gate;

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
        ResourceRequest::class => ResourceRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user) {
            return $user->isAdmin() ? true : null;
        });
    }
}
