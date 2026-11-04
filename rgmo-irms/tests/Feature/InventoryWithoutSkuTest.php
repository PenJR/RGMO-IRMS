<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryWithoutSkuTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_edit_and_import_work_without_sku(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $category = Category::create(['name' => 'Seeds']);
        $data = ['category_id' => $category->id, 'name' => 'Rice without code', 'stock' => 0, 'unit' => 'sacks', 'min_stock' => 5];
        $this->get(route('inventory.create'))->assertOk()->assertDontSee('name="sku"', false);
        $this->post(route('inventory.store'), $data)->assertSessionHasNoErrors();
        $item = InventoryItem::where('name', $data['name'])->firstOrFail();
        $legacyCode = $item->sku;
        $this->assertNotEmpty($legacyCode);
        $this->put(route('inventory.update', $item), array_replace($data, ['name' => 'Updated rice']))->assertSessionHasNoErrors();
        $this->assertSame('Updated rice', $item->fresh()->name);
        $this->assertSame($legacyCode, $item->fresh()->sku);
        $this->get(route('inventory.index'))->assertOk()->assertDontSee($legacyCode);
        $file = UploadedFile::fake()->createWithContent('items.csv', "category,name,stock,unit,min_stock\nSeeds,Imported without code,0,sacks,5\n");
        $this->post(route('inventory.import'), ['file' => $file])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_items', ['name' => 'Imported without code']);
        $this->assertSame(2, InventoryItem::count());
        $csv = $this->get(route('inventory.export-csv'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('SKU', $csv);
        $this->assertStringNotContainsString($legacyCode, $csv);
    }
}
