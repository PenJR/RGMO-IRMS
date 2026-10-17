<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReliabilityUsabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_import_reports_created_and_invalid_rows(): void
    {
        $admin = $this->activeAdmin();
        $csv = implode("\n", [
            'category,name,sku,stock,unit,min_stock',
            'Seeds,Valid Import,IMPORT-VALID,5,pcs,1',
            'Seeds,,IMPORT-INVALID,5,pcs,1',
        ]);

        $response = $this->actingAs($admin)->post(route('inventory.import'), [
            'file' => UploadedFile::fake()->createWithContent('inventory.csv', $csv),
        ]);

        $response
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('warning', fn ($message) => str_contains($message, '1 created') && str_contains($message, '1 skipped'))
            ->assertSessionHas('import_failures', fn ($failures) => str_contains(implode(' ', $failures), 'Row 3'));

        $this->assertDatabaseHas('inventory_items', ['sku' => 'IMPORT-VALID']);
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'IMPORT-INVALID']);
        $this->assertDatabaseHas('categories', ['name' => 'Seeds']);
    }

    public function test_background_notification_count_does_not_create_activity_noise(): void
    {
        $admin = $this->activeAdmin();

        $this->actingAs($admin)
            ->getJson(route('notifications.unread-count'))
            ->assertOk();

        $this->assertDatabaseCount('user_activity_logs', 0);

        $this->get(route('inventory.index'))->assertOk();

        $this->assertDatabaseCount('user_activity_logs', 1);
    }

    public function test_retention_prunes_old_activity_and_read_notifications_only(): void
    {
        $admin = $this->activeAdmin();
        $oldActivity = UserActivityLog::create([
            'user_id' => $admin->id,
            'activity' => 'GET old-page',
        ]);
        $recentActivity = UserActivityLog::create([
            'user_id' => $admin->id,
            'activity' => 'GET recent-page',
        ]);
        UserActivityLog::whereKey($oldActivity)->update(['created_at' => now()->subDays(91)]);

        $oldRead = Notification::create([
            'user_id' => $admin->id,
            'title' => 'Old read',
            'type' => 'system',
            'message' => 'Old read notification',
            'read_at' => now()->subDays(91),
        ]);
        $oldUnread = Notification::create([
            'user_id' => $admin->id,
            'title' => 'Old unread',
            'type' => 'system',
            'message' => 'Old unread notification',
            'read_at' => null,
        ]);
        Notification::whereKey($oldUnread)->update(['created_at' => now()->subDays(91)]);

        (new UserActivityLog)->pruneAll();
        (new Notification)->pruneAll();

        $this->assertModelMissing($oldActivity);
        $this->assertModelExists($recentActivity);
        $this->assertModelMissing($oldRead);
        $this->assertModelExists($oldUnread);
    }

    public function test_layout_uses_local_frontend_assets_and_logo_has_alt_text(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('unpkg.com', false)
            ->assertSee('alt="RGMO-IRMS logo"', false);
    }

    private function activeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
