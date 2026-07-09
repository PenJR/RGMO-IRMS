<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_blocked_when_stock_is_insufficient(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(stock: 2, quantity: 5);

        $this->actingAs($admin)
            ->post(route('requests.approve', $request), ['remarks' => 'Approved'])
            ->assertSessionHasErrors('items');

        $this->assertSame(ResourceRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_requester_can_cancel_pending_request_with_reason(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $request = $this->resourceRequestWithItem(requester: $staff);

        $this->actingAs($staff)
            ->delete(route('requests.destroy', $request), ['remarks' => 'No longer needed'])
            ->assertRedirect(route('requests.show', $request));

        $request->refresh();

        $this->assertSame(ResourceRequest::STATUS_CANCELLED, $request->status);
        $this->assertSame('No longer needed', $request->remarks);
        $this->assertNotNull($request->cancelled_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cancel',
            'module' => 'resource_request',
            'model_id' => $request->id,
        ]);
    }

    public function test_approved_request_can_be_fulfilled_and_deducts_inventory(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(stock: 10, quantity: 4);

        $this->actingAs($admin)
            ->post(route('requests.approve', $request), ['remarks' => 'Ready for release'])
            ->assertRedirect(route('requests.show', $request));

        $this->actingAs($admin)
            ->post(route('requests.fulfill', $request))
            ->assertRedirect(route('requests.show', $request));

        $request->refresh();
        $item = $request->items()->firstOrFail()->item()->firstOrFail();

        $this->assertSame(ResourceRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame(6, $item->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_out',
            'quantity' => 4,
            'destination' => 'resource_request_'.$request->id,
        ]);
        $this->assertTrue(AuditLog::where('action', 'fulfill')
            ->where('module', 'resource_request')
            ->where('model_id', $request->id)
            ->exists());
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function resourceRequestWithItem(?User $requester = null, int $stock = 10, int $quantity = 2): ResourceRequest
    {
        $requester ??= $this->activeUser(User::ROLE_STAFF);
        $category = Category::firstOrCreate(['name' => 'Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->unique()->bothify('REQ-ITEM-####'),
            'stock' => $stock,
            'unit' => 'pcs',
            'min_stock' => 1,
        ]);
        $request = ResourceRequest::create([
            'user_id' => $requester->id,
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Field deployment',
        ]);

        RequestItem::create([
            'resource_request_id' => $request->id,
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
        ]);

        return $request;
    }
}
