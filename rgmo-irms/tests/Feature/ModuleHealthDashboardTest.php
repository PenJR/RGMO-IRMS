<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_module_health_dashboard(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $staff = $this->activeUser(User::ROLE_STAFF);
        $category = Category::create(['name' => 'Supplies']);

        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Low Stock Fertilizer',
            'sku' => 'LOW-FERT',
            'stock' => 2,
            'unit' => 'bag',
            'min_stock' => 5,
        ]);

        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Expiring Chemical',
            'sku' => 'EXP-CHEM',
            'stock' => 20,
            'unit' => 'bottle',
            'min_stock' => 5,
            'has_expiry' => true,
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        ResourceRequest::create([
            'user_id' => $staff->id,
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Field materials',
            'needed_date' => now()->subDay(),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'create',
            'module' => 'inventory',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.health'))
            ->assertOk()
            ->assertSee('Module Health')
            ->assertSee('Low Stock Fertilizer')
            ->assertSee('Expiring Chemical')
            ->assertSee('Field materials')
            ->assertSee('Critical');
    }

    public function test_module_health_json_returns_summary_and_modules(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/health')
            ->assertOk()
            ->assertJsonStructure([
                'generated_at',
                'summary' => [
                    'overall_status',
                    'critical_modules',
                    'warning_modules',
                    'healthy_modules',
                ],
                'modules' => [
                    'inventory',
                    'requests',
                    'security',
                    'audit',
                ],
                'urgent' => [
                    'low_stock_items',
                    'expiring_items',
                    'pending_requests',
                    'recent_audit_logs',
                ],
            ]);
    }

    public function test_staff_cannot_view_module_health_dashboard(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('dashboard.health'))
            ->assertForbidden();
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
