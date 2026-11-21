<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\GeneratedReportSnapshot;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearEndInventorySnapshot;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\YearEndInventorySnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class YearEndInventorySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        Mail::fake();
    }

    public static function roleAccess(): array
    {
        return array_map(fn ($role) => [$role, $role === User::ROLE_ADMIN,
            in_array($role, [User::ROLE_ADMIN, User::ROLE_RGMO_HEAD, User::ROLE_AUDITOR], true)], User::availableRoles());
    }

    #[DataProvider('roleAccess')]
    public function test_year_end_creation_and_reads_follow_the_least_privilege_role_matrix(string $role, bool $canCreate, bool $canRead): void
    {
        [$admin] = $this->inventory();
        $snapshot = $this->capture($admin);
        $actor = $role === User::ROLE_ADMIN ? $admin : $this->actor($role);
        $this->actingAs($actor);
        $index = $this->get(route('reports.year-end.index'));
        $show = $this->get(route('reports.year-end.show', $snapshot));
        $json = $this->getJson(route('reports.year-end.json', $snapshot));
        $preview = $this->get(route('reports.year-end.preview', ['fiscal_year' => 2024]));
        if ($canRead) {
            $index->assertOk();
            $show->assertOk();
            $json->assertOk();
        } else {
            $index->assertForbidden();
            $show->assertForbidden();
            $json->assertForbidden();
        }
        $canCreate ? $preview->assertOk() : $preview->assertForbidden();
        $token = app(YearEndInventorySnapshotService::class)->preview(2024)['preview_token'];
        $response = $this->postJson(route('reports.year-end.store'), $this->payload(2024, $token));
        if ($canCreate) {
            $response->assertRedirect();
            $this->assertDatabaseCount('year_end_inventory_snapshots', 2);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseCount('year_end_inventory_snapshots', 1);
        }
    }

    public function test_capture_freezes_year_end_quantity_labels_prices_thresholds_currency_and_creator_without_touching_stock(): void
    {
        [$admin, $item, $category] = $this->inventory();
        app(InventoryService::class)->recordStockIn($item, 15, 'Later delivery', $admin->id);
        app(InventoryService::class)->recordStockOut($item, 5, 'Later release', $admin->id);
        $transactions = InventoryTransaction::count();
        $snapshot = $this->capture($admin);
        $original = $snapshot->snapshot;
        $service = app(YearEndInventorySnapshotService::class);
        $report = $service->toReport($snapshot);
        $row = $report['items']->sole();
        $this->assertSame(20, $row->stock_at_fiscal_year_end);
        $this->assertSame('low', $row->stock_status_at_fiscal_year_end);
        $this->assertEquals('12.50', $row->price);
        $this->assertSame(25, $row->min_stock);
        $this->assertSame('PHP', $report['currency']['code']);
        $this->assertEquals('250.00', $report['total_value']);
        $this->assertSame('Original year-end administrator', $snapshot->generator_name);
        $this->assertSame('2025-12-31', $snapshot->as_of->toDateString());
        $this->assertSame(30, $item->fresh()->stock);
        $this->assertSame($transactions, InventoryTransaction::count());

        $item->update(['name' => 'Renamed current item', 'price' => 99, 'min_stock' => 1]);
        DB::table('categories')->where('id', $category->id)->update(['name' => 'Renamed current category']);
        DB::table('inventory_items')->where('id', $item->id)->update(['unit' => 'carton']);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $admin->update(['name' => 'Renamed current administrator']);
        $item->delete();
        $report = $service->toReport($snapshot->fresh());
        $row = $report['items']->sole();
        $this->assertSame('Original year-end item', $row->name);
        $this->assertSame('Original year-end category', $row->category->name);
        $this->assertSame('bag', $row->unit);
        $this->assertSame(20, $row->stock_at_fiscal_year_end);
        $this->assertSame(25, $row->min_stock);
        $this->assertSame('PHP', $report['currency']['code']);
        $this->assertSame('Original year-end administrator', $snapshot->fresh()->generator_name);
        $this->assertSame($original, $snapshot->fresh()->snapshot);
    }

    public function test_cutoff_includes_the_last_day_and_archived_items_but_excludes_new_year_items(): void
    {
        [$admin, $item] = $this->inventory();
        $this->travelTo(Carbon::parse('2025-12-31 23:59:59'));
        app(InventoryService::class)->recordStockIn($item, 5, 'Year-end receipt', $admin->id);
        $archived = $this->item('Archived before year end', 4, '1.00');
        $archived->delete();
        $this->travelTo(Carbon::parse('2026-01-01 00:00:00'));
        app(InventoryService::class)->recordStockOut($item, 3, 'New-year release', $admin->id);
        $newItem = $this->item('New-year item', 9, '1.00');
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $snapshot = $this->capture($admin);
        $rows = app(YearEndInventorySnapshotService::class)->toReport($snapshot)['items']->keyBy('id');
        $this->assertCount(2, $rows);
        $this->assertSame(25, $rows[$item->id]->stock_at_fiscal_year_end);
        $this->assertSame(4, $rows[$archived->id]->stock_at_fiscal_year_end);
        $this->assertFalse($rows->has($newItem->id));
        $savedArchived = collect($snapshot->snapshot['items'])->firstWhere('id', $archived->id);
        $this->assertTrue($savedArchived['archived_at_capture']);
    }

    public function test_an_existing_year_reopens_its_original_snapshot_even_after_the_source_is_no_longer_reconstructable(): void
    {
        [$admin, $item] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        $token = $service->preview(2025)['preview_token'];
        $original = $service->capture(2025, $admin, 'Reviewed annual inventory balance.', 'IR-YEAR-END-2025', $token);
        $auditCount = AuditLog::count();
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'user_id' => $admin->id,
            'transaction_type' => 'stock_in', 'quantity' => 100, 'source' => 'Inconsistent later legacy entry']);
        $again = $service->capture(2025, $admin, 'A replay cannot replace the original.', 'DIFFERENT-REFERENCE', $token);
        $this->assertSame($original->id, $again->id);
        $this->assertSame('IR-YEAR-END-2025', $again->reference);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertDatabaseCount('year_end_inventory_snapshots', 1);
        $this->actingAs($admin)->get(route('reports.year-end.preview', ['fiscal_year' => 2025]))
            ->assertRedirect(route('reports.year-end.show', $original));
    }

    public static function staleChanges(): array
    {
        return [['stock'], ['price'], ['name'], ['threshold'], ['currency'], ['category'], ['new_item'], ['archive']];
    }

    #[DataProvider('staleChanges')]
    public function test_a_stale_preview_cannot_permanently_capture_changed_values(string $change): void
    {
        [$admin, $item, $category] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        $token = $service->preview(2025)['preview_token'];
        match ($change) {
            'stock' => $item->update(['stock' => 21]),
            'price' => $item->update(['price' => '13.00']),
            'name' => $item->update(['name' => 'Changed after preview']),
            'threshold' => $item->update(['min_stock' => 1]),
            'currency' => SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']),
            'category' => DB::table('categories')->where('id', $category->id)->update(['name' => 'Changed after preview']),
            'new_item' => $this->createEarlierItem(),
            'archive' => $item->delete(),
        };
        try {
            $service->capture(2025, $admin, 'Reviewed annual inventory balance.', 'IR-YEAR-END-2025', $token);
            $this->fail('A changed preview was sealed without a fresh review.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
        }
    }

    public function test_prices_unknown_at_capture_are_not_reported_as_zero_and_large_valuations_remain_exact(): void
    {
        $admin = $this->actor(User::ROLE_ADMIN);
        $this->travelTo(Carbon::parse('2025-06-01 12:00:00'));
        $unpriced = $this->item('Unknown price item', 3, null);
        $large = $this->item('Large exact valuation', 2147483647, '9999999999.99');
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $snapshot = $this->capture($admin);
        $report = app(YearEndInventorySnapshotService::class)->toReport($snapshot);
        $this->assertFalse($report['valuation_complete']);
        $this->assertSame(1, $report['missing_price_count']);
        $this->assertSame('21474836469978525163.53', $snapshot->total_value);
        $this->assertNull($report['items']->firstWhere('id', $unpriced->id)->price);
        $this->assertNull($report['items']->firstWhere('id', $unpriced->id)->stock_value);
        $this->assertSame('21474836469978525163.53', $report['items']->firstWhere('id', $large->id)->stock_value);
        $this->assertNotEmpty($report['quantity_basis']);
        $this->assertNotEmpty($report['metadata_basis']);
        $this->assertNotEmpty($report['valuation_basis']);
    }

    public static function emptyYears(): array
    {
        return [[1], [2025]];
    }

    #[DataProvider('emptyYears')]
    public function test_empty_closed_years_can_be_reviewed_and_saved_without_inventing_inventory(int $year): void
    {
        $snapshot = $this->capture($this->actor(User::ROLE_ADMIN), $year);
        $report = app(YearEndInventorySnapshotService::class)->toReport($snapshot);
        $this->assertSame(sprintf('%04d-12-31', $year), $snapshot->fresh()->as_of->toDateString());
        $this->assertSame(0, $report['total_items']);
        $this->assertCount(0, $report['items']);
        $this->assertEquals('0.00', $report['total_value']);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_current_future_invalid_years_and_negative_reconstructed_balances_cannot_be_sealed(): void
    {
        [$admin, $item] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        foreach ([0, -1, 2026, 2027] as $year) {
            try {
                $service->preview($year);
                $this->fail('An invalid or open fiscal year was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
            }
        }
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'user_id' => $admin->id,
            'transaction_type' => 'stock_in', 'quantity' => 100, 'source' => 'Unreconciled legacy entry']);
        try {
            $service->preview(2025);
            $this->fail('A negative historical balance was silently converted to zero.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
            $this->assertSame(20, $item->fresh()->stock);
        }
    }

    public function test_failed_audit_persistence_rolls_back_the_whole_snapshot(): void
    {
        [$admin, $item] = $this->inventory();
        $token = app(YearEndInventorySnapshotService::class)->preview(2025)['preview_token'];
        DB::statement("CREATE TRIGGER reject_year_end_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try {
            app(YearEndInventorySnapshotService::class)->capture(2025, $admin, 'Reviewed annual balance.', 'IR-YEAR-END-2025', $token);
            $this->fail('The snapshot was saved without its audit record.');
        } catch (QueryException) {
            $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
            $this->assertSame(20, $item->fresh()->stock);
        } finally {
            DB::statement('DROP TRIGGER reject_year_end_audit');
        }
    }

    public function test_year_end_records_cannot_be_edited_deleted_or_replaced_by_a_duplicate_year(): void
    {
        [$admin] = $this->inventory();
        $snapshot = $this->capture($admin);
        foreach ([fn () => $snapshot->update(['reason' => 'Rewritten annual balance']), fn () => $snapshot->delete(),
            fn () => $snapshot->fresh()->forceFill(['reason' => 'Quietly rewritten annual balance'])->saveQuietly(),
            fn () => $snapshot->fresh()->deleteQuietly(),
            fn () => $snapshot->fresh()->incrementQuietly('total_items')] as $mutation) {
            try {
                $mutation();
                $this->fail('Permanent annual history was changed.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('year_end_inventory_snapshots', 1);
                $this->assertSame('Reviewed annual inventory balance.', $snapshot->fresh()->reason);
            }
        }
        $attributes = $snapshot->fresh()->getAttributes();
        $attributes['id'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('year_end_inventory_snapshots')->insert($attributes);
    }

    public function test_inactive_locked_revoked_or_injected_permissions_cannot_close_a_year(): void
    {
        [$admin] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        $token = $service->preview(2025)['preview_token'];
        $inactive = $this->actor(User::ROLE_ADMIN);
        $inactive->update(['status' => User::STATUS_INACTIVE]);
        $locked = $this->actor(User::ROLE_ADMIN);
        $locked->update(['locked_until' => now()->addHour()]);
        $staff = $this->actor(User::ROLE_STAFF);
        config(['rbac.roles.staff.permissions' => ['close-inventory-year', 'generate-reports', 'manage-inventory']]);
        foreach ([$inactive, $locked, $staff] as $actor) {
            $this->assertCaptureForbidden($actor, $token);
        }
        config(['rbac.roles.admin.permissions' => array_values(array_diff(config('rbac.roles.admin.permissions'), ['close-inventory-year']))]);
        $this->assertCaptureForbidden($admin, $token);
    }

    public function test_historical_inventory_reports_and_category_filters_use_frozen_rows_after_source_changes(): void
    {
        [$admin, $item, $category] = $this->inventory();
        $snapshot = $this->capture($admin);
        $newCategory = Category::create(['name' => 'Replacement current category']);
        DB::table('inventory_items')->where('id', $item->id)->update(['category_id' => $newCategory->id, 'name' => 'Changed current item', 'price' => 99, 'stock' => 900]);
        DB::table('categories')->where('id', $category->id)->delete();
        SystemSetting::set('fiscal_year_override', 2025);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $report = app(ReportService::class)->getInventoryReport(['category_id' => $category->id]);
        $this->assertSame($snapshot->id, $report['year_end_snapshot']->id);
        $this->assertSame('year_end_snapshot', $report['report_source']);
        $this->assertFalse($report['is_provisional']);
        $this->assertSame(20, $report['items']->sole()->stock_at_fiscal_year_end);
        $this->assertSame('Original year-end category', $report['items']->sole()->category->name);
        $this->actingAs($admin)->get(route('reports.inventory', ['category_id' => $category->id]))->assertOk()
            ->assertSee('Original year-end item')->assertSee('Original year-end category')->assertDontSee('Changed current item');
    }

    public function test_preview_is_read_only_stable_across_time_and_cannot_be_used_for_another_year(): void
    {
        [$admin] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        $preview = $service->preview(2025);
        $audits = AuditLog::count();
        $this->travelTo(now()->addDays(1));
        $this->assertSame($preview['preview_token'], $service->preview(2025)['preview_token']);
        $this->assertSame($audits, AuditLog::count());
        $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
        try {
            $service->capture(2024, $admin, 'Reviewed earlier year.', 'IR-2024', $preview['preview_token']);
            $this->fail('The preview approval must apply only to its own fiscal year.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
        }
    }

    public function test_closure_records_one_compact_audit_and_survives_removal_of_the_creator(): void
    {
        [$admin] = $this->inventory();
        $snapshot = $this->capture($admin);
        $saved = $snapshot->snapshot;
        $audit = AuditLog::where('action', 'close_inventory_year')->sole();
        $this->assertSame('reports', $audit->module);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($snapshot->id, $audit->details['year_end_snapshot_id']);
        $this->assertSame(2025, $audit->details['fiscal_year']);
        $this->assertSame($snapshot->reference, $audit->details['reference']);
        $this->assertStringNotContainsString('Original year-end item', json_encode($audit->details));
        $admin->delete();
        $this->assertNull($snapshot->fresh()->user_id);
        $this->assertSame('Original year-end administrator', $snapshot->fresh()->generator_name);
        $this->assertSame($saved, $snapshot->fresh()->snapshot);
    }

    public function test_request_rejects_client_supplied_values_and_requires_review_details_and_a_preview_token(): void
    {
        [$admin] = $this->inventory();
        $token = app(YearEndInventorySnapshotService::class)->preview(2025)['preview_token'];
        $this->actingAs($admin);
        foreach (['snapshot' => ['items' => []], 'currency' => ['code' => 'USD'], 'user_id' => 999,
            'generator_name' => 'Forged author', 'total_value' => '0.00', 'items' => []] as $key => $value) {
            $this->postJson(route('reports.year-end.store'), $this->payload(2025, $token) + [$key => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        foreach (['reason' => ' ', 'reference' => '', 'preview_token' => 'invalid', 'fiscal_year' => 2026] as $key => $value) {
            $this->postJson(route('reports.year-end.store'), array_replace($this->payload(2025, $token), [$key => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
        $this->assertSame(0, AuditLog::where('action', 'close_inventory_year')->count());
    }

    public function test_stale_web_preview_refreshes_for_review_and_json_request_reports_a_conflict(): void
    {
        [$admin, $item] = $this->inventory();
        $token = app(YearEndInventorySnapshotService::class)->preview(2025)['preview_token'];
        $item->update(['price' => 13]);
        $this->actingAs($admin)->postJson(route('reports.year-end.store'), $this->payload(2025, $token))
            ->assertConflict();
        $this->post(route('reports.year-end.store'), $this->payload(2025, $token))
            ->assertStatus(303)->assertRedirect(route('reports.year-end.preview', ['fiscal_year' => 2025]))
            ->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
    }

    public function test_closure_retry_rechecks_current_permission_even_for_an_already_closed_year(): void
    {
        [$admin] = $this->inventory();
        $service = app(YearEndInventorySnapshotService::class);
        $token = $service->preview(2025)['preview_token'];
        $snapshot = $service->capture(2025, $admin, 'Reviewed year-end balance.', 'IR-2025', $token);
        DB::table('users')->where('id', $admin->id)->update(['status' => User::STATUS_INACTIVE]);
        try {
            $service->capture(2025, $admin, 'Repeated review attempt.', 'IR-2025-RETRY', $token);
            $this->fail('An outdated active actor instance must not bypass current account restrictions.');
        } catch (AuthorizationException) {
            $this->assertSame($snapshot->id, YearEndInventorySnapshot::sole()->id);
            $this->assertSame(1, AuditLog::where('action', 'close_inventory_year')->count());
        }
    }

    public function test_every_annual_read_and_export_requires_current_explicit_report_permission(): void
    {
        [$admin] = $this->inventory();
        $snapshot = $this->capture($admin);
        config(['rbac.roles.admin.permissions' => array_values(array_diff(config('rbac.roles.admin.permissions'), ['generate-reports']))]);
        $this->actingAs($admin)->get(route('reports.year-end.index'))->assertForbidden();
        foreach (['show', 'pdf', 'csv', 'json'] as $action) {
            $this->get(route('reports.year-end.'.$action, $snapshot))->assertForbidden();
        }
        $this->assertDatabaseCount('year_end_inventory_snapshots', 1);
    }

    public function test_year_end_views_and_exports_use_frozen_values_after_sources_are_changed(): void
    {
        [$admin, $item, $category] = $this->inventory();
        $snapshot = $this->capture($admin);
        $item->update(['name' => 'Changed live supply', 'price' => 999, 'stock' => 900, 'min_stock' => 1]);
        DB::table('categories')->where('id', $category->id)->update(['name' => 'Changed live category', 'deleted_at' => now()]);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $this->mock(ReportService::class)->shouldNotReceive('getInventoryReport');
        $this->actingAs($admin);
        $view = $this->get(route('reports.year-end.show', $snapshot))->assertOk()
            ->assertSee('Original year-end item')->assertSee('Original year-end category')->assertSee('PHP')
            ->assertDontSee('Changed live supply')->assertDontSee('Changed live category');
        $this->assertSame(20, $view->viewData('report')['items']->sole()->stock_at_fiscal_year_end);
        $json = $this->getJson(route('reports.year-end.json', $snapshot))->assertOk()
            ->assertJsonPath('currency.code', 'PHP')->assertJsonPath('snapshot.items.0.name', 'Original year-end item')
            ->assertJsonPath('snapshot.items.0.price', '12.50')->assertJsonPath('total_value', '250.00');
        $this->assertSame($snapshot->snapshot, $json->json('snapshot'));
        $csv = $this->get(route('reports.year-end.csv', $snapshot))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Original year-end item', $csv);
        $this->assertStringContainsString('Original year-end category', $csv);
        $this->assertStringContainsString('PHP', $csv);
        $this->assertStringContainsString('250.00', $csv);
        $this->assertStringNotContainsString('Changed live supply', $csv);
        $pdf = Mockery::mock(PdfDocument::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('setOptions')->once()->with(['isRemoteEnabled' => false])->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('saved PDF', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('reports.year-end.pdf', Mockery::on(function (array $data) use ($snapshot) {
            $this->assertSame($snapshot->id, $data['snapshot']->id);
            $this->assertSame('Original year-end item', $data['report']['items']->sole()->name);
            $this->assertSame(20, $data['report']['items']->sole()->stock_at_fiscal_year_end);
            $this->assertSame('PHP', $data['report']['currency']['code']);

            return true;
        }))->andReturn($pdf);
        $this->get(route('reports.year-end.pdf', $snapshot))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('year_end_inventory_snapshots', 1);
    }

    public function test_frozen_category_and_low_stock_filters_survive_archiving_and_do_not_change_snapshot_totals(): void
    {
        [$admin, $item, $category] = $this->inventory();
        $this->travelTo(Carbon::parse('2025-06-01 12:00:00'));
        $otherCategory = Category::create(['name' => 'Other original category']);
        $other = InventoryItem::create(['category_id' => $otherCategory->id, 'name' => 'Other original supply',
            'stock' => 100, 'unit' => 'bag', 'price' => '1.00', 'min_stock' => 1]);
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $snapshot = $this->capture($admin);
        DB::table('categories')->where('id', $category->id)->update(['deleted_at' => now()]);
        $item->update(['min_stock' => 1]);
        $other->update(['min_stock' => 1000]);
        $filters = ['snapshot' => $snapshot, 'category_id' => $category->id, 'low_stock' => 1];
        $view = $this->actingAs($admin)->get(route('reports.year-end.show', $filters))->assertOk();
        $this->assertSame(1, $view->viewData('report')['total_items']);
        $this->assertSame('Original year-end item', $view->viewData('report')['items']->sole()->name);
        $csv = $this->get(route('reports.year-end.csv', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('Original year-end item', $csv);
        $this->assertStringNotContainsString('Other original supply', $csv);
        $this->assertSame(2, $snapshot->fresh()->total_items);
        $this->assertSame('350.00', $snapshot->fresh()->total_value);
    }

    public function test_normal_reports_exports_and_saved_generations_keep_the_finalized_year_and_valuation(): void
    {
        [$admin, $item, $category] = $this->inventory();
        $snapshot = $this->capture($admin);
        $item->update(['name' => 'Changed current supply', 'price' => 999, 'stock' => 900]);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        SystemSetting::set('fiscal_year_override', 2026);
        $filters = ['fiscal_year' => 2025, 'category_id' => $category->id];
        $view = $this->actingAs($admin)->get(route('reports.inventory', $filters))->assertOk()
            ->assertSee('Original year-end item')->assertSee('PHP')->assertDontSee('Changed current supply');
        $this->assertSame($snapshot->id, $view->viewData('report')['year_end_snapshot']->id);
        $csv = $this->get(route('reports.inventory.export-csv', $filters))->assertOk();
        $this->assertStringContainsString('PHP', $csv->getContent());
        $this->assertStringContainsString('Original year-end item', $csv->getContent());
        $this->assertStringNotContainsString('Changed current supply', $csv->getContent());
        $this->post(route('reports.history.generate'), $filters + ['report_key' => 'inventory', 'generation_key' => (string) Str::uuid()])
            ->assertRedirect();
        $generation = GeneratedReportSnapshot::sole();
        $this->assertSame(2025, $generation->snapshot['metadata']['fiscal_year']);
        $this->assertSame('PHP', $generation->snapshot['metadata']['currency']['code']);
        $this->assertStringContainsString('Original year-end item', json_encode($generation->snapshot));
        $this->assertStringNotContainsString('Changed current supply', json_encode($generation->snapshot));
        $this->assertSame(2026, SystemSetting::fiscalYear());
    }

    public function test_actual_annual_pdf_renders_unknown_prices_and_exact_large_values_from_the_saved_record(): void
    {
        $admin = $this->actor(User::ROLE_ADMIN);
        $this->actingAs($admin);
        $this->travelTo(Carbon::parse('2025-01-02 12:00:00'));
        $this->item('Unpriced original supply', 3, null);
        $this->item('Precisely valued original supply', 2147483647, '9999999999.99');
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $snapshot = $this->capture($admin);
        $report = app(YearEndInventorySnapshotService::class)->toReport($snapshot);
        $html = view('reports.year-end.pdf', compact('snapshot', 'report'))->render();
        $this->assertStringContainsString('21,474,836,469,978,525,163.53', $html);
        $this->assertStringContainsString('incomplete valuation', $html);
        $this->assertStringContainsString('Unpriced original supply', $html);
        $pdf = $this->get(route('reports.year-end.pdf', $snapshot))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_annual_csv_neutralizes_formula_labels_and_saved_html_is_escaped(): void
    {
        [$admin, $item, $category] = $this->inventory();
        $item->update(['name' => '=HYPERLINK("https://example.test")']);
        DB::table('categories')->where('id', $category->id)->update(['name' => '<script>alert("saved")</script>']);
        $service = app(YearEndInventorySnapshotService::class);
        $snapshot = $service->capture(2025, $admin, '@untrusted review note', '+IR-2025', $service->preview(2025)['preview_token']);
        $this->actingAs($admin)->get(route('reports.year-end.show', $snapshot))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("saved")</script>', false);
        $csv = $this->get(route('reports.year-end.csv', $snapshot))->assertOk()->streamedContent();
        $cells = collect(array_map('str_getcsv', explode("\n", trim($csv))))->flatten()->all();
        $this->assertContains('\'=HYPERLINK("https://example.test")', $cells);
        $this->assertContains("'@untrusted review note", $cells);
        $this->assertContains("'+IR-2025", $cells);
        $this->assertSame('=HYPERLINK("https://example.test")', $snapshot->snapshot['items'][0]['name']);
    }

    public function test_locked_and_inactive_accounts_cannot_read_or_export_saved_annual_history(): void
    {
        [$admin] = $this->inventory();
        $snapshot = $this->capture($admin);
        $inactive = $this->actor(User::ROLE_ADMIN);
        $inactive->update(['status' => User::STATUS_INACTIVE]);
        $locked = $this->actor(User::ROLE_AUDITOR);
        $locked->update(['locked_until' => now()->addHour()]);
        foreach ([$inactive, $locked] as $actor) {
            foreach (['show', 'pdf', 'csv', 'json'] as $action) {
                $this->actingAs($actor)->getJson(route('reports.year-end.'.$action, $snapshot))->assertForbidden();
            }
        }
    }

    private function assertCaptureForbidden(User $actor, string $token): void
    {
        try {
            app(YearEndInventorySnapshotService::class)->capture(2025, $actor, 'Reviewed annual balance.', 'IR-YEAR-END-2025', $token);
            $this->fail('Unauthorized annual closure was accepted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('year_end_inventory_snapshots', 0);
        }
    }

    private function capture(User $actor, int $year = 2025): YearEndInventorySnapshot
    {
        $service = app(YearEndInventorySnapshotService::class);

        return $service->capture($year, $actor, 'Reviewed annual inventory balance.', 'IR-YEAR-END-'.$year, $service->preview($year)['preview_token']);
    }

    private function payload(int $year, string $token): array
    {
        return ['fiscal_year' => $year, 'reason' => 'Reviewed annual inventory balance.', 'reference' => 'IR-YEAR-END-'.$year, 'preview_token' => $token];
    }

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE, 'name' => 'Original year-end administrator']);
    }

    private function inventory(): array
    {
        $admin = $this->actor(User::ROLE_ADMIN);
        $this->actingAs($admin);
        SystemSetting::set('default_currency', ['code' => 'PHP', 'symbol' => '₱']);
        $this->travelTo(Carbon::parse('2025-01-02 12:00:00'));
        $item = $this->item('Original year-end item', 20, '12.50');
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));

        return [$admin, $item, $item->category];
    }

    private function item(string $name, int $stock, ?string $price): InventoryItem
    {
        return InventoryItem::create(['category_id' => Category::firstOrCreate(['name' => 'Original year-end category'])->id,
            'name' => $name, 'sku' => (string) Str::uuid(), 'stock' => $stock, 'unit' => 'bag', 'price' => $price, 'min_stock' => 25]);
    }

    private function createEarlierItem(): InventoryItem
    {
        $this->travelTo(Carbon::parse('2025-07-01 12:00:00'));
        $item = $this->item('Additional earlier inventory', 1, '1.00');
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));

        return $item;
    }
}
