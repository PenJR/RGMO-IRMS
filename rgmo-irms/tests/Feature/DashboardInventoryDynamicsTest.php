<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
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
            ->assertSee('Filter inventory dynamics by item')
            ->assertSee('Hybrid Corn Seed (SEED-HYBRID-DYN)');

        Carbon::setTestNow();
    }
}
