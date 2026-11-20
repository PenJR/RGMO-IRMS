<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\ResourceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class HighPriorityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_timezone_defaults_to_manila(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
    }

    public function test_inventory_csv_and_excel_exports_include_more_than_250_items(): void
    {
        $this->freezeTime();
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $category = Category::create(['name' => 'Bulk Export']);

        foreach (range(1, 251) as $number) {
            InventoryItem::create([
                'category_id' => $category->id,
                'name' => 'Bulk item '.$number,
                'sku' => sprintf('BULK-%03d', $number),
                'stock' => 10,
                'unit' => 'pcs',
                'min_stock' => 2,
            ]);
        }

        $csv = $this->actingAs($admin)->get(route('inventory.export-csv'));

        $csv->assertOk();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv->streamedContent());
        rewind($stream);
        $csvRows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $csvRows[] = $row;
        }
        fclose($stream);
        $expectedNames = array_map(fn ($number) => 'Bulk item '.$number, range(1, 251));
        $headings = array_shift($csvRows);
        $this->assertSame('Name', $headings[1]);
        $this->assertCount(251, $csvRows);
        $this->assertEqualsCanonicalizing($expectedNames, array_column($csvRows, 1));

        $excel = $this->get(route('inventory.export-excel'))->assertOk()
            ->assertDownload('inventory_'.now()->format('Y-m-d_H-i-s').'.xlsx');
        $path = $excel->baseResponse->getFile()->getPathname();
        $workbook = null;
        try {
            $workbook = IOFactory::load($path);
            $excelRows = $workbook->getActiveSheet()->toArray();
            $this->assertSame($headings, array_shift($excelRows));
            $this->assertCount(251, $excelRows);
            $this->assertEqualsCanonicalizing($expectedNames, array_column($excelRows, 1));
        } finally {
            $workbook?->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_audit_export_includes_records_beyond_the_first_page(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);

        foreach (range(1, 55) as $number) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'export-check-'.$number,
                'module' => 'testing',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('reports.audit-trail.export-csv'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('export-check-1', $content);
        $this->assertStringContainsString('export-check-55', $content);
    }

    public function test_completed_request_cannot_be_fulfilled_twice_and_is_in_issuance_report(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $staff = $this->activeUser(User::ROLE_STAFF);
        $category = Category::create(['name' => 'Issuance Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Priority Seed Pack',
            'sku' => 'PRIORITY-SEED',
            'stock' => 20,
            'unit' => 'pack',
            'min_stock' => 2,
        ]);
        $project = Project::create([
            'name' => 'Priority Project',
            'code' => 'PRIORITY-PROJECT',
            'status' => Project::STATUS_ACTIVE,
            'start_date' => today()->subDay(),
            'end_date' => today()->addMonth(),
        ]);
        $request = ResourceRequest::create([
            'user_id' => $staff->id,
            'project_id' => $project->id,
            'status' => ResourceRequest::STATUS_APPROVED,
            'purpose' => 'Priority test release',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        RequestItem::create([
            'resource_request_id' => $request->id,
            'inventory_item_id' => $item->id,
            'quantity' => 4,
        ]);

        $this->actingAs($admin);
        $service = app(ResourceRequestService::class);
        $service->fulfillRequest($request);

        $this->assertNotNull($request->fresh()->fulfilled_at);

        try {
            $service->fulfillRequest($request->fresh());
            $this->fail('A completed request was fulfilled twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(16, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('resource_usages', 0);

        $this->get(route('reports.supplies-issuance', [
            'month' => $request->fresh()->fulfilled_at->month,
            'year' => $request->fresh()->fulfilled_at->year,
        ]))
            ->assertOk()
            ->assertSee('Priority Seed Pack');
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
