<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackFixesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    public function test_invalid_months_and_years_are_rejected_for_report_pages_and_pdfs(): void
    {
        $this->actingAs($this->admin());
        foreach (['reports.monthly-inventory', 'reports.supplies-issuance'] as $route) {
            foreach ([$route, $route.'.export-pdf'] as $endpoint) {
                foreach ([0, -1, 'abc', 1.5, 10000, '', ['2026']] as $year) {
                    $this->getJson(route($endpoint, ['year' => $year]))->assertUnprocessable()->assertJsonValidationErrors('year');
                }
                foreach ([0, 13, 'abc', ['1']] as $month) {
                    $this->getJson(route($endpoint, ['month' => $month]))->assertUnprocessable()->assertJsonValidationErrors('month');
                }
            }
            $this->from(route($route))->get(route($route, ['year' => 0]))
                ->assertRedirect(route($route))->assertSessionHasErrors('year');
            $this->get(route($route))->assertOk()->assertSee('Enter a year from 1 to 9999.');
            foreach ([1, 2026, 9999] as $year) {
                $this->get(route($route, ['year' => $year, 'month' => 12]))->assertOk();
            }
        }
    }

    public function test_module_dropdown_uses_stored_modules_and_retains_the_selected_filter(): void
    {
        $admin = $this->admin();
        AuditLog::log($admin->id, 'create', 'inventory');
        AuditLog::log($admin->id, 'update', 'user_management');
        $this->actingAs($admin)->get(route('reports.audit-trail', ['module' => 'inventory']))
            ->assertOk()->assertSee('select name="module"', false)
            ->assertSee('value="inventory" selected', false)->assertSee('User Management')
            ->assertViewHas('report', fn ($logs) => $logs->count() === 1 && $logs->first()->module === 'inventory');
    }

    public function test_mark_all_reads_every_page_only_for_the_current_user(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        foreach (range(1, 25) as $i) {
            Notification::create(['user_id' => $admin->id, 'type' => 'system', 'message' => "Alert $i"]);
        }
        $foreign = Notification::create(['user_id' => $other->id, 'type' => 'system', 'message' => 'Private']);
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('id="notifications-mark-all"', false);
        $this->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $admin->notifications()->unread()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_inventory_exports_include_all_matching_rows_and_exclude_other_items(): void
    {
        $category = Category::create(['name' => 'Seeds']);
        foreach (range(1, 12) as $i) {
            InventoryItem::create(['category_id' => $category->id, 'name' => "Rice $i", 'sku' => "RICE-$i", 'stock' => 2, 'min_stock' => 5, 'unit' => 'kg']);
        }
        InventoryItem::create(['category_id' => $category->id, 'name' => 'Other', 'sku' => 'OTHER', 'stock' => 50, 'min_stock' => 5, 'unit' => 'kg']);
        $filters = ['search' => 'Rice', 'status' => 'low', 'category_id' => $category->id, 'sort' => 'name', 'direction' => 'desc'];
        $this->actingAs($this->admin())->get(route('inventory.index', $filters))->assertOk()->assertSee('Export CSV')->assertSee('Export PDF');
        $csv = $this->get(route('inventory.export-csv', $filters))->assertOk()->streamedContent();
        $this->assertCount(13, array_filter(explode("\n", trim($csv))));
        $this->assertStringContainsString('RICE-12', $csv);
        $this->assertStringNotContainsString('OTHER', $csv);
        $this->get(route('inventory.export-pdf', $filters))->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');

        Pdf::shouldReceive('loadView')->once()->with('reports.inventory-pdf', \Mockery::on(
            fn ($data) => $data['report']['items']->count() === 12 && $data['report']['items']->every(fn ($item) => str_starts_with($item->sku, 'RICE-'))
        ))->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('download')->once()->andReturn(response('PDF'));
        $this->get(route('inventory.export-pdf', $filters))->assertOk();
    }

    public function test_inventory_exports_require_authentication(): void
    {
        foreach (['inventory.export-csv', 'inventory.export-pdf'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
