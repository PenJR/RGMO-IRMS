<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryTypeLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]));
    }

    private function data(): array
    {
        return ['name' => 'Feed', 'sku' => 'FEED-1', 'stock' => 10, 'min_stock' => 2, 'unit' => 'sacks'];
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create($this->data() + ['category_id' => Category::create(['name' => 'Animal Feed'])->id]);
    }

    public function test_new_types_can_be_created_and_reused_in_web_and_api(): void
    {
        $this->post(route('inventory.store'), $this->data() + ['category_name' => 'Animal Feed'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $item = InventoryItem::firstOrFail();
        $this->assertSame('Animal Feed', $item->category->name);
        $this->get(route('inventory.create'))->assertOk()->assertSee('Animal Feed');
        $this->postJson('/api/inventory', array_replace($this->data(), ['sku' => 'FEED-2', 'category_name' => 'animal feed']))
            ->assertCreated()->assertJsonPath('category_id', $item->category_id);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_invalid_or_ambiguous_types_do_not_create_items_or_categories(): void
    {
        $category = Category::create(['name' => 'Tools']);
        foreach ([[], ['category_name' => '<b></b>'], ['category_name' => str_repeat('a', 256)],
            ['category_id' => $category->id, 'category_name' => 'Feed']] as $type) {
            $this->postJson('/api/inventory', $this->data() + $type)->assertUnprocessable();
        }
        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_edit_page_locks_fields_and_other_fields_can_still_be_updated(): void
    {
        $item = $this->item();
        $this->get(route('inventory.edit', $item))->assertOk()
            ->assertSee('value="sacks" readonly', false)->assertSee('value="Animal Feed" readonly', false)
            ->assertDontSee('name="category_id"', false)->assertDontSee('name="unit"', false);
        $data = $this->data();
        unset($data['unit']);
        $this->put(route('inventory.update', $item), array_replace($data, ['stock' => 12]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(12, $item->fresh()->stock);
        $this->assertSame('sacks', $item->fresh()->unit);
    }

    public function test_web_cannot_change_type_or_unit_and_rolls_back_other_changes(): void
    {
        $item = $this->item();
        $other = Category::create(['name' => 'Tools']);
        $this->put(route('inventory.update', $item), array_replace($this->data(), [
            'category_id' => $other->id, 'unit' => 'kg', 'stock' => 99,
        ]))->assertSessionHasErrors(['category_id', 'unit']);
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame('sacks', $item->fresh()->unit);
        $this->assertSame('Animal Feed', $item->fresh()->category->name);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_api_rejects_changes_but_accepts_original_values(): void
    {
        $item = $this->item();
        $other = Category::create(['name' => 'Tools']);
        $this->putJson('/api/inventory/'.$item->id, ['category_id' => $other->id, 'unit' => 'kg'])
            ->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'unit']);
        $this->putJson('/api/inventory/'.$item->id, ['category_id' => (string) $item->category_id, 'unit' => 'sacks', 'name' => 'Updated Feed'])
            ->assertOk()->assertJsonPath('name', 'Updated Feed');
    }

    public function test_import_skips_locked_changes_without_restoring_or_creating_types(): void
    {
        $item = $this->item();
        $item->delete();
        $csv = "category,name,sku,stock,unit,min_stock\nChanged Type,Changed Feed,FEED-1,99,kg,2\nNew Type,New Feed,FEED-2,5,sacks,1\n";
        $this->post(route('inventory.import'), ['file' => UploadedFile::fake()->createWithContent('items.csv', $csv)])
            ->assertRedirect()->assertSessionHas('warning')->assertSessionHas('import_failures');
        $this->assertTrue($item->fresh()->trashed());
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame('sacks', $item->fresh()->unit);
        $this->assertDatabaseMissing('categories', ['name' => 'Changed Type']);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'FEED-2', 'stock' => 5]);
    }

    public function test_assigned_resource_types_cannot_be_renamed_or_deleted(): void
    {
        $item = $this->item();
        $this->putJson('/api/ops/categories/'.$item->category_id, ['name' => 'Tools'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->deleteJson('/api/ops/categories/'.$item->category_id)
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertSame('Animal Feed', $item->fresh()->category->name);
    }

    public function test_view_only_users_cannot_create_resource_types_through_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_PROJECT_MANAGER, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]));
        $this->postJson('/api/inventory', $this->data() + ['category_name' => 'Feed'])->assertForbidden();
        $this->assertDatabaseCount('categories', 0);
    }
}
