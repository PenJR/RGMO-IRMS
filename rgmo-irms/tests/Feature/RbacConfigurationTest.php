<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that every available role has rbac configuration.
     */
    public function test_every_available_role_has_rbac_configuration(): void
    {
        $configuredRoles = array_keys(config('rbac.roles'));

        $this->assertEmpty(array_diff(User::availableRoles(), $configuredRoles));
        $this->assertEmpty(array_diff($configuredRoles, User::availableRoles()));
    }

    /**
     * Verify that role permissions are defined permissions.
     */
    public function test_role_permissions_are_defined_permissions(): void
    {
        $definedPermissions = array_keys(config('rbac.permissions'));

        foreach (config('rbac.roles') as $role => $settings) {
            $unknownPermissions = array_diff($settings['permissions'], $definedPermissions);

            $this->assertEmpty($unknownPermissions, "{$role} has undefined permissions.");
        }
    }

    /**
     * Verify that admin user forms include all available roles.
     */
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

    /**
     * Verify that sidebar uses permissions for role specific links.
     */
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

    /**
     * Verify that admin settings can update scalar setting values.
     */
    public function test_admin_can_update_resource_low_stock_threshold_setting(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $category = Category::create(['name' => 'Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Bond Paper',
            'sku' => 'OFF-PAPER-SETTINGS',
            'stock' => 8,
            'unit' => 'ream',
            'min_stock' => 5,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'inventory_item_id' => $item->id,
                'min_stock' => 12,
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame(12, $item->fresh()->min_stock);
    }

    public function test_theme_control_is_only_rendered_on_the_settings_page(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="colorTheme"', false);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('id="colorTheme"', false)
            ->assertSee('Appearance');
    }

    /**
     * Handle active user.
     */
    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
