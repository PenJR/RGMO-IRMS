<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FiscalYearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_select_a_previous_fiscal_year_and_return_to_automatic(): void
    {
        $admin = $this->activeAdmin();
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        $this->assertSame($currentYear, SystemSetting::fiscalYear());

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Fiscal Year')
            ->assertSee('Automatic ('.$currentYear.')');

        $this->actingAs($admin)
            ->post(route('admin.settings.fiscal-year.update'), ['fiscal_year' => $previousYear])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame($previousYear, SystemSetting::fiscalYear());

        $this->actingAs($admin)
            ->post(route('admin.settings.fiscal-year.update'), ['fiscal_year' => ''])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame($currentYear, SystemSetting::fiscalYear());
        Carbon::setTestNow(Carbon::create($currentYear + 1, 1, 1));
        $this->assertSame($currentYear + 1, SystemSetting::fiscalYear());
    }

    public function test_inventory_report_reconstructs_historical_year_end_stock_without_resetting_live_stock(): void
    {
        $admin = $this->activeAdmin();
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;
        $category = Category::create(['name' => 'Supplies']);

        Carbon::setTestNow(Carbon::create($previousYear, 1, 2, 12));
        $item = app(InventoryService::class)->createItem([
            'category_id' => $category->id,
            'name' => 'Carryover Supplies',
            'sku' => 'CARRYOVER-SUPPLIES',
            'stock' => 20,
            'unit' => 'piece',
            'min_stock' => 25,
        ], $admin->id);
        Carbon::setTestNow();

        $currentYearReport = app(ReportService::class)->getInventoryReport();
        $this->assertSame($currentYear, $currentYearReport['fiscal_year']);
        $this->assertSame(20, $currentYearReport['items']->firstWhere('id', $item->id)->stock_at_fiscal_year_end);

        $inventory = app(InventoryService::class);
        $inventory->recordStockIn($item, 15, 'Current year delivery', $admin->id);
        $inventory->recordStockOut($item, 5, 'Current year issue', $admin->id);
        $this->assertSame(30, $item->fresh()->stock);

        $this->actingAs($admin)
            ->post(route('admin.settings.fiscal-year.update'), ['fiscal_year' => $previousYear])
            ->assertRedirect(route('admin.settings.index'));

        $report = app(ReportService::class)->getInventoryReport();
        $reportedItem = $report['items']->firstWhere('id', $item->id);

        $this->assertSame($previousYear, $report['fiscal_year']);
        $this->assertSame(20, $reportedItem->stock_at_fiscal_year_end);
        $this->assertSame('low', $reportedItem->stock_status_at_fiscal_year_end);
        $this->assertSame(20, app(ReportService::class)->getInventoryReport(['low_stock' => true])['items']->first()->stock_at_fiscal_year_end);
        $this->assertSame(30, $item->fresh()->stock);

        $this->actingAs($admin)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('Stock at year end')
            ->assertSee('20 piece')
            ->assertSee((string) $previousYear);
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
