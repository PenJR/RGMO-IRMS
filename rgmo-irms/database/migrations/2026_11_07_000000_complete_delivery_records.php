<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('procurement_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('receipt_key')->nullable()->index();
            $table->string('delivery_receipt_number', 100)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('unit_of_measurement', 30)->nullable();
        });

        // Preserve old references as entered; they may be receipt, invoice, or other references.
        DB::table('deliveries')->orderBy('id')->chunkById(500, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                $line = DB::table('procurement_items')
                    ->where('procurement_id', $delivery->procurement_id)
                    ->where('inventory_item_id', $delivery->inventory_item_id)->first();
                $purchase = DB::table('procurements')->where('id', $delivery->procurement_id)->first();
                $item = DB::table('inventory_items')->where('id', $delivery->inventory_item_id)->first();
                DB::table('deliveries')->where('id', $delivery->id)->update([
                    'procurement_item_id' => $line?->id,
                    'receipt_key' => $delivery->submission_key,
                    'unit_cost' => $line?->unit_cost ?? $purchase?->unit_cost,
                    'unit_of_measurement' => $line?->unit_of_measurement ?? $item?->unit,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_item_id');
            $table->dropIndex(['receipt_key']);
            $table->dropColumn(['receipt_key', 'delivery_receipt_number', 'invoice_number', 'unit_cost', 'unit_of_measurement']);
        });
    }
};
