<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_another_users_password(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $user = $this->activeUser(User::ROLE_STAFF);
        $oldRememberToken = $user->remember_token;
        $this->createSession('target-user-session', $user);

        $this->actingAs($admin)
            ->post(route('admin.users.reset-password', $user), [
                'password' => 'NewSecure123!',
                'password_confirmation' => 'NewSecure123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('NewSecure123!', $user->fresh()->password));
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-user-session']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'password_reset',
            'module' => 'user_management',
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    public function test_admin_user_page_contains_password_change_form(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $user = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee('action="'.route('admin.users.reset-password', $user).'"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_non_admin_roles_cannot_change_another_users_password(): void
    {
        $user = $this->activeUser(User::ROLE_STAFF);

        foreach ([User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER, User::ROLE_RGMO_HEAD] as $role) {
            $actor = $this->activeUser($role);

            $this->actingAs($actor)
                ->post(route('admin.users.reset-password', $user), [
                    'password' => 'Unauthorized123!',
                    'password_confirmation' => 'Unauthorized123!',
                ])
                ->assertForbidden();
        }

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'password_reset',
            'model_id' => $user->id,
        ]);
    }

    public function test_non_admin_cannot_change_password_through_api(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password',
                'new_password' => 'Unauthorized123!',
                'new_password_confirmation' => 'Unauthorized123!',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $staff->fresh()->password));
    }

    public function test_non_admin_profile_does_not_offer_password_change_form(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Password Managed by Administrator')
            ->assertDontSee('action="'.route('password.update').'"', false);
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function createSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.20',
            'user_agent' => 'Test browser',
            'payload' => base64_encode('test-session'),
            'last_activity' => now()->subMinute()->timestamp,
        ]);
    }
}
