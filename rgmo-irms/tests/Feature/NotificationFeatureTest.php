<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_request_submission_notifies_admin_and_rgmo_head(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF, ['name' => 'Maria Staff']);
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $head = $this->activeUser(User::ROLE_RGMO_HEAD);
        $item = $this->inventoryItem('Hybrid Rice Seeds');

        $response = $this->actingAs($staff)->post(route('requests.store'), [
            'purpose' => 'Field demo',
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $resourceRequest = ResourceRequest::firstOrFail();
        $response->assertRedirect(route('requests.show', $resourceRequest));

        foreach ([$admin, $head] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'title' => 'New Resource Request',
                'type' => 'resource_request',
                'message' => 'Maria Staff submitted a request for Hybrid Rice Seeds.',
                'sender_id' => $staff->id,
                'related_request_id' => $resourceRequest->id,
                'read_at' => null,
            ]);
        }
    }

    public function test_request_approval_and_rejection_notify_requester(): void
    {
        $requester = $this->activeUser(User::ROLE_STAFF);
        $admin = $this->activeUser(User::ROLE_ADMIN);

        $approvedRequest = $this->resourceRequestFor($requester);
        $this->actingAs($admin)
            ->post(route('requests.approve', $approvedRequest), ['remarks' => 'Approved'])
            ->assertRedirect(route('requests.show', $approvedRequest));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'title' => 'Resource Request Approved',
            'type' => 'resource_request_approved',
            'message' => 'Your resource request has been approved.',
            'sender_id' => $admin->id,
            'related_request_id' => $approvedRequest->id,
        ]);

        $rejectedRequest = $this->resourceRequestFor($requester);
        $this->actingAs($admin)
            ->post(route('requests.reject', $rejectedRequest), ['remarks' => 'Insufficient stock'])
            ->assertRedirect(route('requests.show', $rejectedRequest));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'title' => 'Resource Request Rejected',
            'type' => 'resource_request_rejected',
            'message' => 'Your resource request has been rejected.',
            'sender_id' => $admin->id,
            'related_request_id' => $rejectedRequest->id,
        ]);
    }

    public function test_admin_login_creates_login_history_and_authorized_notifications(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN, [
            'name' => 'Ada Admin',
            'email' => 'admin@example.test',
        ]);
        $head = $this->activeUser(User::ROLE_RGMO_HEAD);
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->post(route('login'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $admin->id,
            'user_role' => User::ROLE_ADMIN,
        ]);

        $this->assertNotNull(LoginHistory::where('user_id', $admin->id)->first()?->login_at);

        foreach ([$admin, $head] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'title' => 'Admin Login',
                'type' => 'admin_login',
                'message' => 'Admin Ada Admin logged in to the system.',
                'sender_id' => $admin->id,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $staff->id,
            'type' => 'admin_login',
        ]);
    }

    public function test_notification_api_lists_and_marks_notifications_read(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $first = Notification::create([
            'user_id' => $staff->id,
            'title' => 'First',
            'type' => 'resource_request_approved',
            'message' => 'Your resource request has been approved.',
        ]);
        Notification::create([
            'user_id' => $staff->id,
            'title' => 'Second',
            'type' => 'resource_request_rejected',
            'message' => 'Your resource request has been rejected.',
        ]);

        $this->actingAs($staff)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonCount(2, 'data');

        $this->actingAs($staff)
            ->postJson(route('notifications.read', $first))
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->assertNotNull($first->fresh()->read_at);

        $this->actingAs($staff)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $staff->notifications()->unread()->count());
    }

    private function activeUser(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function inventoryItem(string $name = 'Rice Seeds'): InventoryItem
    {
        $category = Category::firstOrCreate(['name' => 'Seeds']);

        return InventoryItem::create([
            'category_id' => $category->id,
            'name' => $name,
            'sku' => fake()->unique()->bothify('ITEM-####'),
            'stock' => 50,
            'unit' => 'bag',
            'min_stock' => 5,
        ]);
    }

    private function resourceRequestFor(User $user): ResourceRequest
    {
        $request = ResourceRequest::create([
            'user_id' => $user->id,
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Field use',
        ]);

        RequestItem::create([
            'resource_request_id' => $request->id,
            'inventory_item_id' => $this->inventoryItem()->id,
            'quantity' => 1,
        ]);

        return $request;
    }
}
