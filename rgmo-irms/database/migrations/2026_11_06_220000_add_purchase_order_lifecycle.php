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
            $table->string('status', 30)->default('draft')->after('po_number');
            $table->foreignId('approved_by')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->foreignId('cancelled_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });

        if (Schema::hasColumn('procurements', 'created_at')) {
            // Existing procurements predate the PO workflow. Preserve their ability to receive by treating them as approved historical orders.
            DB::table('procurements')->where('status', 'draft')->update([
                'status' => 'approved',
                'approved_at' => DB::raw('COALESCE(created_at, updated_at)'),
            ]);
            if (Schema::hasTable('procurement_items')) {
                DB::table('procurements')->whereIn('id', DB::table('procurement_items')->select('procurement_id')->groupBy('procurement_id')->havingRaw('SUM(received_quantity) > 0 AND SUM(received_quantity) < SUM(ordered_quantity)'))->update(['status' => 'partially_received']);
                DB::table('procurements')->whereIn('id', DB::table('procurement_items')->select('procurement_id')->groupBy('procurement_id')->havingRaw('SUM(received_quantity) >= SUM(ordered_quantity) AND SUM(ordered_quantity) > 0'))->update(['status' => 'completed']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'status', 'approved_at', 'rejected_at', 'rejection_reason',
                'cancelled_at', 'cancellation_reason',
            ]);
        });
    }
};
