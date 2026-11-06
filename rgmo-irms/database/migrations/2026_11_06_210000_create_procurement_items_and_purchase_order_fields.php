<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable()->after('id');
            $table->date('order_date')->nullable()->after('purchased_on');
            $table->date('expected_delivery_date')->nullable()->after('order_date');
            $table->foreignId('prepared_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('total_amount', 14, 2)->default(0)->after('unit_cost');
        });

        $usedPoNumbers = [];
        DB::table('procurements')->orderBy('id')->get()->each(function ($procurement) use (&$usedPoNumbers) {
            $candidate = trim((string) $procurement->or_number);
            $base = $candidate !== '' ? $candidate : 'PO-'.$procurement->id;
            $poNumber = $base;
            $suffix = 2;
            while (isset($usedPoNumbers[$poNumber]) || DB::table('procurements')->where('po_number', $poNumber)->where('id', '!=', $procurement->id)->exists()) {
                $poNumber = $base.'-'.$suffix++;
            }
            $usedPoNumbers[$poNumber] = true;
            DB::table('procurements')->where('id', $procurement->id)->update([
                'po_number' => $poNumber,
                'order_date' => $procurement->purchased_on,
                'prepared_by' => $procurement->created_by,
                'total_amount' => $this->decimalMultiply((string) $procurement->quantity, (string) $procurement->unit_cost),
            ]);
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->unique('po_number');
        });

        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('ordered_quantity');
            $table->string('unit_of_measurement', 30);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->timestamps();

            $table->unique(['procurement_id', 'inventory_item_id']);
        });

        $procurements = DB::table('procurements')->orderBy('id')->get();
        foreach ($procurements as $procurement) {
            $item = DB::table('inventory_items')->where('id', $procurement->inventory_item_id)->first();
            if (! $item) {
                continue;
            }
            $received = (int) DB::table('deliveries')->where('procurement_id', $procurement->id)
                ->where('inventory_item_id', $procurement->inventory_item_id)->sum('quantity');
            DB::table('procurement_items')->insert([
                'procurement_id' => $procurement->id,
                'inventory_item_id' => $procurement->inventory_item_id,
                'ordered_quantity' => $procurement->quantity,
                'unit_of_measurement' => $item->unit,
                'unit_cost' => $procurement->unit_cost,
                'subtotal' => $this->decimalMultiply((string) $procurement->quantity, (string) $procurement->unit_cost),
                'received_quantity' => min((int) $procurement->quantity, $received),
                'created_at' => $procurement->created_at,
                'updated_at' => $procurement->updated_at,
            ]);
        }
    }

    private function decimalMultiply(string $quantity, string $unitCost): string
    {
        if (function_exists('bcmul')) {
            return bcmul($quantity, $unitCost, 2);
        }

        $negative = str_starts_with($unitCost, '-');
        $parts = explode('.', ltrim($unitCost, '-'), 2);
        $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '0', 2, '0');
        $product = (int) $quantity * $cents * ($negative ? -1 : 1);

        return sprintf('%s%d.%02d', $product < 0 ? '-' : '', intdiv(abs($product), 100), abs($product) % 100);
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropUnique(['po_number']);
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn(['po_number', 'order_date', 'expected_delivery_date', 'total_amount']);
        });
    }
};
