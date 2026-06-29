<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_item_can_be_created_with_expiry_enabled(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Chemicals']);

        $response = $this->actingAs($admin)->post(route('inventory.store'), [
            'category_id' => $category->id,
            'name' => 'Seed Treatment',
            'sku' => 'CHEM-SEED-TREAT',
            'stock' => 15,
            'unit' => 'pcs',
            'min_stock' => 5,
            'price' => 125,
            'has_expiry' => '1',
            'expiry_date' => '2026-12-31',
        ]);

        $item = InventoryItem::where('sku', 'CHEM-SEED-TREAT')->firstOrFail();

        $response->assertRedirect(route('inventory.show', $item));
        $this->assertTrue($item->has_expiry);
        $this->assertSame('2026-12-31', $item->expiry_date->format('Y-m-d'));
    }

    public function test_disabling_expiry_clears_existing_expiry_date(): void
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
            'sku' => 'SEED-EXPIRY',
            'stock' => 20,
            'unit' => 'pcs',
            'min_stock' => 5,
            'price' => 50,
            'has_expiry' => true,
            'expiry_date' => '2026-10-15',
        ]);

        $response = $this->actingAs($admin)->put(route('inventory.update', $item), [
            'category_id' => $category->id,
            'name' => 'Rice Seeds',
            'sku' => 'SEED-EXPIRY',
            'stock' => 20,
            'unit' => 'pcs',
            'min_stock' => 5,
            'price' => 50,
            'has_expiry' => '0',
        ]);

        $response->assertRedirect(route('inventory.show', $item));
        $item->refresh();

        $this->assertFalse($item->has_expiry);
        $this->assertNull($item->expiry_date);
    }
}
