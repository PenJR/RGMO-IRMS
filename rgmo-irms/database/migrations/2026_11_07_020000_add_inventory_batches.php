<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false);
        });
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_legacy')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['inventory_item_id', 'batch_number']);
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->string('submission_fingerprint', 64)->nullable();
        });
        Schema::create('inventory_batch_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_batch_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->restrictOnDelete();
            $table->integer('quantity');
            $table->timestamps();
            $table->unique(['inventory_batch_id', 'inventory_transaction_id'], 'batch_transaction_unique');
        });

        DB::table('inventory_items')->orderBy('id')->chunkById(250, function ($items) {
            foreach ($items as $item) {
                $tracked = 0;
                foreach (DB::table('deliveries')->where('inventory_item_id', $item->id)->orderBy('id')->get() as $delivery) {
                    $batchId = DB::table('inventory_batches')->insertGetId([
                        'inventory_item_id' => $item->id, 'batch_number' => null,
                        'expiry_date' => $item->has_expiry ? $item->expiry_date : null,
                        'stock' => $delivery->remaining_quantity, 'is_legacy' => true,
                        'created_by' => $delivery->received_by,
                        'created_at' => $delivery->created_at, 'updated_at' => $delivery->updated_at,
                    ]);
                    DB::table('deliveries')->where('id', $delivery->id)->update(['inventory_batch_id' => $batchId]);
                    $tracked += $delivery->remaining_quantity;
                }
                if ($item->stock > $tracked) {
                    DB::table('inventory_batches')->insert([
                        'inventory_item_id' => $item->id, 'batch_number' => null,
                        'expiry_date' => $item->has_expiry ? $item->expiry_date : null,
                        'stock' => $item->stock - $tracked, 'is_legacy' => true,
                        'created_at' => $item->created_at, 'updated_at' => $item->updated_at,
                    ]);
                }
                if ($item->has_expiry) {
                    DB::table('inventory_items')->where('id', $item->id)->update(['track_batches' => true]);
                }
            }
        });

        // Link only movements whose existing delivery provenance identifies the batch.
        // Original transaction metadata, quantities, dates and balances are retained.
        DB::table('inventory_transactions')->orderBy('id')->chunkById(500, function ($transactions) {
            foreach ($transactions as $transaction) {
                $meta = json_decode($transaction->meta ?? '{}', true) ?: [];
                $movements = [];
                if ($transaction->transaction_type === 'stock_in') {
                    $deliveryId = $meta['delivery_id'] ?? null;
                    if (! $deliveryId && preg_match('/^DEL-(\d+)$/', $transaction->reference ?? '', $matches)) {
                        $deliveryId = (int) $matches[1];
                    }
                    $batchId = $deliveryId ? DB::table('deliveries')->where('id', $deliveryId)
                        ->where('inventory_item_id', $transaction->inventory_item_id)->value('inventory_batch_id') : null;
                    if ($batchId) {
                        $movements[$batchId] = $transaction->quantity;
                    }
                } else {
                    foreach ($meta['deliveries'] ?? [] as $allocation) {
                        $batchId = DB::table('deliveries')->where('id', $allocation['delivery_id'])
                            ->where('inventory_item_id', $transaction->inventory_item_id)->value('inventory_batch_id');
                        if ($batchId) {
                            $movements[$batchId] = ($movements[$batchId] ?? 0) - $allocation['quantity'];
                        }
                    }
                }
                foreach ($movements as $batchId => $quantity) {
                    DB::table('inventory_batch_transactions')->insert([
                        'inventory_batch_id' => $batchId, 'inventory_transaction_id' => $transaction->id,
                        'quantity' => $quantity, 'created_at' => $transaction->created_at, 'updated_at' => $transaction->updated_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batch_transactions');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_batch_id');
            $table->dropColumn('submission_fingerprint');
        });
        Schema::dropIfExists('inventory_batches');
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn('track_batches'));
    }
};
