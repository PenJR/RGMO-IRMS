<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiModuleSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_api_module_endpoints_respond_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $paths = [
            '/api/auth/me',
            '/api/inventory',
            '/api/inventory/alerts/low-stock',
            '/api/notifications',
            '/api/notifications/unread-count',
            '/api/users',
            '/api/ops/categories',
            '/api/ops/transactions',
            '/api/ops/dashboard/admin/stats',
            '/api/ops/dashboard/admin/total-users',
            '/api/ops/dashboard/admin/total-inventory-items',
            '/api/ops/dashboard/admin/recent-activities',
            '/api/ops/requests/pending/list',
            '/api/ops/approvals/queue',
            '/api/ops/dashboard/approver/pending',
            '/api/ops/dashboard/approver/stats',
            '/api/ops/reports/inventory',
            '/api/ops/reports/stock-movement',
            '/api/ops/reports/requests',
            '/api/ops/reports/consumption',
            '/api/ops/audit/logs',
            '/api/ops/settings',
            '/api/ops/settings/roles-permissions',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)
                ->getJson($path)
                ->assertOk("Path [{$path}] failed to respond.");
        }
    }
}
