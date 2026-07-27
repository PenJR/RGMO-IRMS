<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_command_generates_an_alert(): void
    {
        User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Maintenance Supplies']);
        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Low Maintenance Item',
            'sku' => 'LOW-MAINTENANCE',
            'stock' => 1,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);

        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $this->assertDatabaseHas('notifications', [
            'type' => 'low_stock',
        ]);
        $this->assertSame(1, Notification::where('type', 'low_stock')->count());
    }

    public function test_manual_backup_does_not_report_a_failed_backup_as_successful(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        Artisan::shouldReceive('call')->once()->with('backup:run')->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn('sqlite3: command not found');

        $this->actingAs($admin)
            ->from(route('admin.backup.index'))
            ->post(route('admin.backup.run'))
            ->assertRedirect(route('admin.backup.index'))
            ->assertSessionHasErrors('backup');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'backup_failed',
            'module' => 'system',
        ]);
    }
}
