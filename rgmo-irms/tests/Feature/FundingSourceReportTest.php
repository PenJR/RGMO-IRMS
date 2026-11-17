<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FundingSource;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FundingSourceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_balances_include_new_inactive_legacy_and_unclassified_funding_without_relabeling_history(): void
    {
        $admin = $this->admin();
        $special = FundingSource::create([
            'code' => 'SPECIAL-RESEARCH', 'name' => 'Special research allocation', 'status' => FundingSource::STATUS_ACTIVE,
        ]);
        $legacy = FundingSource::create([
            'code' => 'LEGACY-RESEARCH', 'name' => 'Historical research allocation', 'status' => FundingSource::STATUS_INACTIVE,
        ]);
        $unused = FundingSource::create([
            'code' => 'UNUSED', 'name' => 'Unused historical source', 'status' => FundingSource::STATUS_INACTIVE,
        ]);
        $inventory = app(InventoryService::class);

        Carbon::setTestNow(Carbon::create(2026, 1, 15, 12));
        $item = $inventory->createItem([
            'category_id' => Category::create(['name' => 'Funding report supplies'])->id,
            'name' => 'Funding report item', 'stock' => 6, 'unit' => 'pieces', 'min_stock' => 0, 'price' => 10,
        ], $admin->id);
        $inventory->recordStockIn($item, 10, 'JF delivery', $admin->id, 'JF');
        $inventory->recordStockIn($item, 3, 'Research delivery', $admin->id, $special->code);
        $inventory->recordStockIn($item, 5, 'Legacy delivery', $admin->id, $legacy->code);
        $inventory->recordStockIn($item, 7, 'Unregistered legacy delivery', $admin->id, 'External legacy label');
        $inventory->recordStockIn($item, 2, 'Blank source delivery', $admin->id, '');
        $inventory->recordStockIn($item, 1, 'Whitespace source delivery', $admin->id, '   ');
        $inventory->recordStockOut($item, 4, 'January release', $admin->id);

        Carbon::setTestNow(Carbon::create(2026, 2, 1));
        $inventory->recordStockIn($item, 2, 'February delivery', $admin->id, $special->code);
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 23, 59, 59));
        $inventory->recordStockOut($item, 3, 'February release', $admin->id);
        Carbon::setTestNow(Carbon::create(2026, 3, 1));
        $inventory->recordStockIn($item, 4, 'March delivery', $admin->id, 'JF');

        $service = app(ReportService::class);
        $sources = $service->monthlyInventoryFundingSources();
        $report = $service->getMonthlyInventoryReport(2, 2026);
        $row = $report->first();

        $this->assertSame($special->label, $sources[$special->code]);
        $this->assertSame($legacy->label, $sources[$legacy->code]);
        $this->assertArrayHasKey($unused->code, $sources);
        $this->assertSame('External legacy label', $sources['External legacy label']);
        $this->assertSame('Unclassified', $sources['']);
        $this->assertSame(10, $row->beginning_balances['JF']->qty);
        $this->assertSame(3, $row->beginning_balances[$special->code]->qty);
        $this->assertSame(5, $row->beginning_balances[$legacy->code]->qty);
        $this->assertSame(7, $row->beginning_balances['External legacy label']->qty);
        $this->assertSame(5, $row->beginning_balances['']->qty);
        $this->assertSame(0, $row->beginning_balances[$unused->code]->qty);
        $this->assertSame(30, $row->total_beginning_balance_qty);
        $this->assertSame(2, $row->delivered_qty);
        $this->assertSame(3, $row->withdrawals_qty);
        $this->assertSame(29, $row->ending_balance_qty);
        $this->assertEquals(290, $row->ending_balance_value);
        $this->assertSame(33, $item->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', ['inventory_item_id' => $item->id, 'funding_source' => 'External legacy label']);
        $this->assertDatabaseHas('inventory_transactions', ['inventory_item_id' => $item->id, 'funding_source' => '   ']);
    }

    public function test_monthly_html_and_pdf_include_database_managed_headings_even_without_inventory_rows(): void
    {
        $source = FundingSource::create([
            'code' => 'RESEARCH-2027', 'name' => 'New research funding', 'status' => FundingSource::STATUS_INACTIVE,
        ]);
        $this->actingAs($this->admin());

        $response = $this->get(route('reports.monthly-inventory', ['month' => 2, 'year' => 2026]));
        $response->assertOk()
            ->assertSee($source->label.' Qty')
            ->assertSee('Unclassified Qty')
            ->assertSee('No inventory data available for the selected month.')
            ->assertViewHas('fundingSources', fn (array $sources) => $sources[$source->code] === $source->label);

        $pdfMarkup = view('reports.monthly-inventory-pdf', [
            'report' => $response->viewData('report'),
            'fundingSources' => $response->viewData('fundingSources'),
            'month' => 2, 'year' => 2026,
        ])->render();
        $this->assertStringContainsString($source->label.' Qty', $pdfMarkup);
        $this->assertStringContainsString('Unclassified Qty', $pdfMarkup);
        $this->assertStringContainsString('No inventory data available for the selected month.', $pdfMarkup);
        $this->assertStringNotContainsString('DAH Qty', $pdfMarkup);

        $this->get(route('reports.monthly-inventory.export-pdf', ['month' => 2, 'year' => 2026]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_source_added_after_the_heading_snapshot_remains_in_totals_and_keeps_its_recorded_label(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 15, 12));
        $admin = $this->admin();
        $inventory = app(InventoryService::class);
        $item = $inventory->createItem([
            'category_id' => Category::create(['name' => 'Concurrent funding supplies'])->id,
            'name' => 'Concurrent funding item', 'stock' => 0, 'unit' => 'pieces', 'min_stock' => 0,
        ], $admin->id);
        $inventory->recordStockIn($item, 3, 'Initial source delivery', $admin->id, 'JF');
        $service = app(ReportService::class);
        $headings = $service->monthlyInventoryFundingSources();
        unset($headings['']);

        $newSource = FundingSource::create([
            'code' => 'NEW-SOURCE', 'name' => 'Newly registered funding', 'status' => FundingSource::STATUS_ACTIVE,
        ]);
        $inventory->recordStockIn($item, 4, 'New source delivery', $admin->id, $newSource->code);

        $snapshotRow = $service->getMonthlyInventoryReport(2, 2026, $headings)->first();
        $this->assertSame(3, $snapshotRow->beginning_balances['JF']->qty);
        $this->assertSame(4, $snapshotRow->beginning_balances['']->qty);
        $this->assertSame(7, $snapshotRow->total_beginning_balance_qty);
        $this->assertSame(7, $snapshotRow->ending_balance_qty);
        $this->assertArrayNotHasKey($newSource->code, $snapshotRow->beginning_balances);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id, 'funding_source' => $newSource->code, 'quantity' => 4,
        ]);

        $refreshedRow = $service->getMonthlyInventoryReport(2, 2026)->first();
        $this->assertSame(4, $refreshedRow->beginning_balances[$newSource->code]->qty);
        $this->assertSame(0, $refreshedRow->beginning_balances['']->qty);
        $this->assertSame(7, $refreshedRow->ending_balance_qty);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }
}
