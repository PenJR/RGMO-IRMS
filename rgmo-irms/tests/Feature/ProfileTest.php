<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that profile page is displayed.
     */
    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    /**
     * Verify that profile information can be updated.
     */
    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    /**
     * Verify that email verification status is unchanged when the email address is unchanged.
     */
    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_save_a_personal_sidebar_order(): void
    {
        $user = User::factory()->create();
        $order = ['requests', 'inventory', 'dashboard', 'notifications'];

        $this->actingAs($user)
            ->putJson(route('profile.sidebar-order.update'), ['order' => $order])
            ->assertOk()
            ->assertJsonPath('message', 'Sidebar order saved.');

        $this->assertSame($order, $user->refresh()->sidebar_order);
    }

    public function test_sidebar_order_rejects_unknown_or_duplicate_items(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('profile.sidebar-order.update'), [
                'order' => ['dashboard', 'dashboard', 'unauthorized-module'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order.1', 'order.2']);

        $this->assertNull($user->refresh()->sidebar_order);
    }

    public function test_user_can_restore_the_default_sidebar_order(): void
    {
        $user = User::factory()->create([
            'sidebar_order' => ['requests', 'dashboard', 'inventory'],
        ]);

        $this->actingAs($user)
            ->delete(route('profile.sidebar-order.reset'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'sidebar-order-reset');

        $this->assertNull($user->refresh()->sidebar_order);
    }

    /**
     * Verify that user can delete their account.
     */
    public function test_user_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }

    /**
     * Verify that correct password must be provided to delete account.
     */
    public function test_admin_can_delete_another_account(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.users.destroy', $user));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh());
    }
}
