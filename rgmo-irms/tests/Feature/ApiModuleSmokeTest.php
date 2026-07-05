<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiModuleSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that main api module endpoints respond for admin.
     */
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

    /**
     * Verify API permission denials return JSON instead of the web 403 page.
     */
    public function test_api_permission_denial_returns_json(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    /**
     * Verify framework-rendered API authorization errors are JSON.
     */
    public function test_api_abort_forbidden_returns_json(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->getJson("/api/ops/notifications/users/{$otherUser->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden']);
    }

    /**
     * Verify unauthenticated API requests return JSON instead of redirects.
     */
    public function test_unauthenticated_api_request_returns_json(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
