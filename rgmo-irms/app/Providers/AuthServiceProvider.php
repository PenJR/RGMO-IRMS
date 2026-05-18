<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Policies\UserPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\ResourceRequestPolicy;

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
        //
    }
}
