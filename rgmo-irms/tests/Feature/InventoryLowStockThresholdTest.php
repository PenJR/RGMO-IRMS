<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLowStockThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_uses_each_resource_threshold(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Supplies']);

        $lowItem = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Bond Paper',
            'sku' => 'OFF-PAPER',
            'stock' => 8,
            'unit' => 'ream',
            'min_stock' => 10,
        ]);

        $healthyItem = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Printer Ink',
            'sku' => 'OFF-INK',
            'stock' => 8,
            'unit' => 'bottle',
            'min_stock' => 5,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.low-stock'))
            ->assertOk()
            ->assertSee($lowItem->name)
            ->assertSee($healthyItem->name)
            ->assertSee('10 ream')
            ->assertSee('value="5"', false);

        $this->assertTrue($lowItem->isLowStock());
        $this->assertFalse($healthyItem->isLowStock());
    }

    public function test_resource_low_stock_threshold_can_be_updated(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Seeds']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Rice Seeds',
            'sku' => 'SEED-RICE',
            'stock' => 8,
            'unit' => 'bag',
            'min_stock' => 5,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('inventory.update-low-stock-threshold', $item), [
                'min_stock' => 12,
            ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertSame(12, $item->min_stock);
        $this->assertTrue($item->isLowStock());
    }
}
