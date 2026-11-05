<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve historical approvals and stock. Existing shortages must not prevent startup.
        $overcommitted = DB::table('request_items as ri')->join('resource_requests as r', 'r.id', '=', 'ri.resource_request_id')
            ->join('inventory_items as i', 'i.id', '=', 'ri.inventory_item_id')
            ->where('r.status', 'approved')->whereNull('r.deleted_at')
            ->select('i.id')->groupBy('i.id', 'i.stock')->havingRaw('SUM(ri.quantity) > i.stock')->exists();
        if ($overcommitted) {
            Log::warning('Existing approved requests exceed stock. Historical approvals are preserved; replenish affected inventory before issuing stock or approving more requests.');
        }
        Schema::table('request_items', function (Blueprint $table) {
            $table->unsignedInteger('withdrawn_quantity')->default(0);
        });
        // Completed historical requests were already issued; never deduct them again.
        DB::table('request_items')->whereIn('resource_request_id', DB::table('resource_requests')->where('status', 'completed')->select('id'))
            ->update(['withdrawn_quantity' => DB::raw('quantity')]);
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_number', 80);
            $table->string('or_number', 100);
            $table->date('purchased_on');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->string('funding_source')->default('RGMO');
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->date('received_on');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining_quantity');
            $table->string('reference', 100)->nullable();
            $table->uuid('submission_key')->unique();
            $table->timestamps();
        });
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('received_by');
            $table->date('withdrawn_on');
            $table->uuid('submission_key')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('withdrawal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_items');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('procurements');
        Schema::table('request_items', fn (Blueprint $table) => $table->dropColumn('withdrawn_quantity'));
    }
};
