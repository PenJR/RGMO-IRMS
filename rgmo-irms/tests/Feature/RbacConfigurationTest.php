<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_available_role_has_rbac_configuration(): void
    {
        $configuredRoles = array_keys(config('rbac.roles'));

        $this->assertEmpty(array_diff(User::availableRoles(), $configuredRoles));
        $this->assertEmpty(array_diff($configuredRoles, User::availableRoles()));
    }

    public function test_role_permissions_are_defined_permissions(): void
    {
        $definedPermissions = array_keys(config('rbac.permissions'));

        foreach (config('rbac.roles') as $role => $settings) {
            $unknownPermissions = array_diff($settings['permissions'], $definedPermissions);

            $this->assertEmpty($unknownPermissions, "{$role} has undefined permissions.");
        }
    }

    public function test_admin_user_forms_include_all_available_roles(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $user = $this->activeUser(User::ROLE_STAFF);

        foreach ([route('admin.users.create'), route('admin.users.edit', $user)] as $url) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertOk();

            foreach (User::availableRoles() as $role) {
                $response->assertSee('value="' . $role . '"', false);
            }
        }
    }

    public function test_sidebar_uses_permissions_for_role_specific_links(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('inventory.index') . '"', false)
            ->assertDontSee('href="' . route('reports.inventory') . '"', false)
            ->assertDontSee('href="' . route('ai-forecasting.index') . '"', false);

        $manager = $this->activeUser(User::ROLE_PROJECT_MANAGER);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('inventory.index') . '"', false)
            ->assertSee('href="' . route('reports.inventory') . '"', false)
            ->assertSee('href="' . route('ai-forecasting.index') . '"', false);
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
