<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardInventoryDynamicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_resource_readiness_and_replenishment_suggestions(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Field Supplies']);
        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Healthy Seed Bags',
            'sku' => 'SEED-HEALTHY',
            'stock' => 20,
            'unit' => 'bag',
            'min_stock' => 10,
        ]);
        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Low Seed Bags',
            'sku' => 'SEED-LOW',
            'stock' => 3,
            'unit' => 'bag',
            'min_stock' => 10,
        ]);

        $readiness = app(ReportService::class)->getDashboardStats()['charts']['resource_readiness'];

        $this->assertSame([
            'percent' => 50,
            'ready_items' => 1,
            'total_items' => 2,
        ], $readiness);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Resource Readiness')
            ->assertSee('50%')
            ->assertSee('1 of 2 ready')
            ->assertSee('dashboard-kpi--interactive', false)
            ->assertSee('0 overdue · 0 new this week')
            ->assertSee('Add 13 bags');
    }

    public function test_inventory_dynamics_can_be_filtered_by_item_and_uses_historical_stock(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Seeds']);
        $item = InventoryItem::forceCreate([
            'category_id' => $category->id,
            'name' => 'Hybrid Corn Seed',
            'sku' => 'SEED-HYBRID-DYN',
            'stock' => 12,
            'unit' => 'bag',
            'min_stock' => 5,
            'created_at' => now()->subMonths(3),
            'updated_at' => now(),
        ]);

        InventoryTransaction::forceCreate([
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_in',
            'quantity' => 10,
            'created_at' => now()->subDays(16),
            'updated_at' => now()->subDays(16),
        ]);
        InventoryTransaction::forceCreate([
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_out',
            'quantity' => 3,
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $chart = app(ReportService::class)->getDashboardStats()['charts']['inventory_levels'];
        $itemSeries = collect($chart['items'])->firstWhere('id', $item->id);

        $this->assertNotNull($itemSeries);
        $this->assertSame([5, 5, 5, 15, 12, 12], $itemSeries['weekly']);
        $this->assertSame($itemSeries['weekly'], $chart['weekly']['data']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Filter stock levels by item')
            ->assertSee('Hybrid Corn Seed (SEED-HYBRID-DYN)');

        Carbon::setTestNow();
    }

    public function test_rgmo_head_dashboard_shows_available_reserved_and_recently_utilized_resources(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_RGMO_HEAD, 'status' => User::STATUS_ACTIVE]);
        $requester = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => User::STATUS_ACTIVE]);
        $category = Category::create(['name' => 'Inputs']);
        $item = InventoryItem::create(['category_id' => $category->id, 'name' => 'Organic Fertilizer', 'sku' => 'FERT-ORG', 'stock' => 26, 'unit' => 'sacks', 'min_stock' => 5]);
        $request = ResourceRequest::create([
            'user_id' => $requester->id,
            'project_id' => Project::create(['name' => 'Demo Field', 'code' => 'DEMO', 'status' => Project::STATUS_ACTIVE])->id,
            'status' => ResourceRequest::STATUS_PARTIALLY_WITHDRAWN,
            'tracks_utilization' => true,
            'purpose' => 'Planting',
        ]);
        $line = RequestItem::create(['resource_request_id' => $request->id, 'inventory_item_id' => $item->id, 'quantity' => 12, 'withdrawn_quantity' => 4]);
        ResourceUsage::create(['request_item_id' => $line->id, 'inventory_item_id' => $item->id, 'project_id' => $request->project_id, 'user_id' => $requester->id, 'quantity' => 3, 'used_on' => today(), 'activity' => 'Field application']);
        ResourceRequest::create(['user_id' => $requester->id, 'project_id' => $request->project_id, 'status' => ResourceRequest::STATUS_PENDING_RGMO_HEAD, 'purpose' => 'Final signature']);

        $analytics = app(ReportService::class)->getLeadershipResourceAnalytics();
        $this->assertSame(18, $analytics['summary']['available']);
        $this->assertSame(8, $analytics['summary']['reserved']);
        $this->assertSame(3, $analytics['summary']['utilized_30_days']);
        $this->assertSame(1, $analytics['summary']['awaiting_final_approval']);
        $this->assertSame(['Organic Fertilizer (sacks)'], $analytics['chart']['labels']);

        $this->actingAs($head)->get(route('dashboard'))->assertOk()
            ->assertSee('id="rgmoResourceOverviewTitle"', false)
            ->assertSee('Resources & usage')
            ->assertSee('rgmoResourcePositionChart', false)
            ->assertSee('Awaiting final approval');
    }
}
