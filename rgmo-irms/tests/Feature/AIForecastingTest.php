<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIForecastingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that forecasting page renders real inventory predictions.
     */
    public function test_forecasting_page_renders_real_inventory_predictions(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_PROJECT_MANAGER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Fertilizers']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'NPK 14-14-14',
            'sku' => 'FERT-NPK-TEST',
            'stock' => 8,
            'unit' => 'bag',
            'min_stock' => 10,
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'user_id' => $manager->id,
            'transaction_type' => 'stock_out',
            'quantity' => 30,
            'destination' => 'Field project',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($manager)
            ->get(route('ai-forecasting.index'))
            ->assertOk()
            ->assertSee('Demand Prediction by Item')
            ->assertSee('NPK 14-14-14')
            ->assertSee('Critical')
            ->assertSee('Reorder 12 bag');
    }

    /**
     * Verify that staff without forecast permission cannot view forecasts.
     */
    public function test_staff_without_forecast_permission_cannot_view_forecasts(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('ai-forecasting.index'))
            ->assertForbidden();
    }
}
