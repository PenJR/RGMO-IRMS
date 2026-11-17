<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FundingSourceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_legacy_codes_and_registers_sources_from_purchases_and_transactions(): void
    {
        $migration = require database_path('migrations/2026_11_17_000000_create_funding_sources_table.php');
        $migration->down();

        try {
            [$admin, $item] = $this->legacyContext();
            $jfPurchase = $this->legacyPurchase($admin, $item, 'JF', 'JF-OLD');
            $legacyPurchase = $this->legacyPurchase($admin, $item, 'DA Grant', 'DA-OLD');
            $blankPurchase = $this->legacyPurchase($admin, $item, '', 'BLANK-OLD');
            $longCode = str_repeat('L', 150);
            $longPurchase = $this->legacyPurchase($admin, $item, $longCode, 'LONG-OLD');
            foreach (['Transaction-only legacy', '0', '', null] as $code) {
                DB::table('inventory_transactions')->insert([
                    'inventory_item_id' => $item->id,
                    'user_id' => $admin->id,
                    'transaction_type' => 'stock_in',
                    'quantity' => 1,
                    'funding_source' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $purchaseSnapshots = DB::table('procurements')->orderBy('id')->pluck('funding_source', 'id')->all();
            $transactionSnapshots = DB::table('inventory_transactions')->orderBy('id')->pluck('funding_source', 'id')->all();

            $migration->up();

            $this->assertSame($purchaseSnapshots, DB::table('procurements')->orderBy('id')->pluck('funding_source', 'id')->all());
            $this->assertSame($transactionSnapshots, DB::table('inventory_transactions')->orderBy('id')->pluck('funding_source', 'id')->all());
            $this->assertDatabaseHas('funding_sources', ['code' => 'JF', 'status' => FundingSource::STATUS_ACTIVE]);
            $this->assertDatabaseHas('funding_sources', ['code' => 'STF', 'status' => FundingSource::STATUS_ACTIVE]);
            foreach (['DA Grant', 'Transaction-only legacy', '0', $longCode] as $code) {
                $this->assertDatabaseHas('funding_sources', ['code' => $code, 'status' => FundingSource::STATUS_INACTIVE]);
            }
            $this->assertDatabaseMissing('funding_sources', ['code' => '']);
            $this->assertSame(FundingSource::where('code', 'JF')->value('id'), DB::table('procurements')->where('id', $jfPurchase)->value('funding_source_id'));
            $this->assertSame(FundingSource::where('code', 'DA Grant')->value('id'), DB::table('procurements')->where('id', $legacyPurchase)->value('funding_source_id'));
            $this->assertSame(FundingSource::where('code', $longCode)->value('id'), DB::table('procurements')->where('id', $longPurchase)->value('funding_source_id'));
            $this->assertNull(DB::table('procurements')->where('id', $blankPurchase)->value('funding_source_id'));
            $this->assertSame(7, $item->fresh()->stock);
        } finally {
            if (! Schema::hasTable('funding_sources')) {
                $migration->up();
            }
        }
    }

    public function test_rollback_removes_only_catalog_linkage_and_keeps_original_purchase_and_stock_history(): void
    {
        [$admin, $item] = $this->legacyContext();
        $source = FundingSource::create(['code' => 'DONOR', 'name' => 'Donor program']);
        $purchaseId = $this->legacyPurchase($admin, $item, 'DONOR', 'DONOR-HISTORY');
        DB::table('procurements')->where('id', $purchaseId)->update(['funding_source_id' => $source->id]);
        $transactionId = DB::table('inventory_transactions')->insertGetId([
            'inventory_item_id' => $item->id,
            'user_id' => $admin->id,
            'transaction_type' => 'stock_in',
            'quantity' => 7,
            'funding_source' => 'DONOR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_17_000000_create_funding_sources_table.php');
        $migration->down();

        try {
            $this->assertFalse(Schema::hasTable('funding_sources'));
            $this->assertFalse(Schema::hasColumn('procurements', 'funding_source_id'));
            $this->assertDatabaseHas('procurements', ['id' => $purchaseId, 'funding_source' => 'DONOR', 'quantity' => 5]);
            $this->assertDatabaseHas('inventory_transactions', ['id' => $transactionId, 'funding_source' => 'DONOR', 'quantity' => 7]);
            $this->assertSame(7, $item->fresh()->stock);
        } finally {
            $migration->up();
        }

        $this->assertSame('DONOR', DB::table('procurements')->where('id', $purchaseId)->value('funding_source'));
        $this->assertNotNull(DB::table('procurements')->where('id', $purchaseId)->value('funding_source_id'));
    }

    private function legacyContext(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Migration supplies'])->id,
            'name' => 'Migration stock',
            'unit' => 'box',
            'stock' => 7,
            'min_stock' => 0,
        ]);

        return [$admin, $item];
    }

    private function legacyPurchase(User $admin, InventoryItem $item, string $code, string $po): int
    {
        return DB::table('procurements')->insertGetId([
            'inventory_item_id' => $item->id,
            'created_by' => $admin->id,
            'supplier_name' => 'Historical supplier',
            'supplier_number' => '09170000000',
            'or_number' => $po,
            'po_number' => $po,
            'purchased_on' => today()->toDateString(),
            'quantity' => 5,
            'unit_cost' => '20.00',
            'funding_source' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
