<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->boolean('is_adjustment')->default(false)->index();
            $table->text('reason')->nullable();
            $table->string('incident_report_reference', 100)->nullable();
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after')->nullable();
        });

        // Identify old edit adjustments without inventing incident references or balances.
        DB::table('inventory_transactions')->where('meta->adjustment', true)
            ->update(['is_adjustment' => true]);
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['is_adjustment']);
            $table->dropColumn(['is_adjustment', 'reason', 'incident_report_reference', 'stock_before', 'stock_after']);
        });
    }
};
