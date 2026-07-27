<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_every_primary_module_page(): void
    {
        [$admin, $staff, $item, $request, $project] = $this->records();

        $paths = [
            route('dashboard'),
            route('dashboard.staff'),
            route('profile.edit'),
            route('ai-forecasting.index'),
            route('inventory.index'),
            route('inventory.create'),
            route('inventory.show', $item),
            route('inventory.edit', $item),
            route('inventory.low-stock'),
            route('requests.index'),
            route('requests.create'),
            route('requests.show', $request),
            route('requests.edit', $request),
            route('requests.pending'),
            route('projects.index'),
            route('projects.create'),
            route('projects.show', $project),
            route('projects.edit', $project),
            route('notifications.index'),
            route('reports.inventory'),
            route('reports.resource-usage'),
            route('reports.audit-trail'),
            route('reports.requests'),
            route('reports.biological-assets'),
            route('reports.supplies-issuance'),
            route('reports.monthly-inventory'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.show', $staff),
            route('admin.users.edit', $staff),
            route('admin.users.login-history', $staff),
            route('admin.login-logs.index'),
            route('admin.backup.index'),
            route('admin.settings.index'),
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk("Page [{$path}] failed to render.");
        }
    }

    public function test_report_and_inventory_exports_download_successfully(): void
    {
        [$admin] = $this->records();

        $paths = [
            route('inventory.export-csv'),
            route('inventory.export-excel'),
            route('reports.inventory.export-csv'),
            route('reports.inventory.export-pdf'),
            route('reports.biological-assets.export-pdf'),
            route('reports.supplies-issuance.export-pdf'),
            route('reports.monthly-inventory.export-pdf'),
            route('reports.audit-trail.export-csv'),
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk("Export [{$path}] failed.");
        }
    }

    /**
     * @return array{User, User, InventoryItem, ResourceRequest, Project}
     */
    private function records(): array
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Smoke Test Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Smoke Test Item',
            'sku' => 'SMOKE-ITEM',
            'stock' => 25,
            'unit' => 'piece',
            'min_stock' => 5,
            'price' => 10,
        ]);
        $request = ResourceRequest::create([
            'user_id' => $staff->id,
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Module smoke test',
            'needed_date' => now()->addWeek(),
        ]);
        $project = Project::create([
            'name' => 'Module Smoke Project',
            'code' => 'SMOKE-PROJECT',
            'status' => Project::STATUS_ACTIVE,
            'start_date' => today(),
        ]);

        return [$admin, $staff, $item, $request, $project];
    }
}
