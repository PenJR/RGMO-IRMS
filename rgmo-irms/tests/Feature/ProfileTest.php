<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_profile_displays_cookie_settings_and_active_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $this->createSession('other-session', $user, '192.168.1.22', 'Mozilla/5.0 (Windows NT 10.0; rv:120.0) Gecko/20100101 Firefox/120.0');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Cookies &amp; Sessions', false)
            ->assertSee('Essential cookies only')
            ->assertSee('Firefox on Windows')
            ->assertSee('192.168.1.22');
    }

    public function test_user_can_revoke_one_owned_browser_session(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createSession('owned-session', $user);
        $this->createSession('foreign-session', $otherUser);

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy', 'owned-session'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'session-revoked');

        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
    }

    public function test_user_can_revoke_all_other_owned_browser_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createSession('owned-session-one', $user);
        $this->createSession('owned-session-two', $user);
        $this->createSession('foreign-session', $otherUser);

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy-others'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'other-sessions-revoked');

        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session-two']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createSession('foreign-session', $otherUser);

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy', 'foreign-session'))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
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

    private function createSession(
        string $id,
        User $user,
        string $ipAddress = '192.168.1.20',
        string $userAgent = 'Mozilla/5.0 (Linux; Android 14) Chrome/126.0'
    ): void {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => base64_encode('test-session'),
            'last_activity' => now()->subMinutes(5)->timestamp,
        ]);
    }
}
