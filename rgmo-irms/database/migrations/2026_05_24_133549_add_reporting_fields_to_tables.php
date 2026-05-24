<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('role');
            }
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'planting_date')) {
                $table->timestamp('planting_date')->nullable()->after('description');
            }
        });

        Schema::table('resource_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resource_requests', 'ris_no')) {
                $table->string('ris_no')->nullable()->after('id');
            }
            if (!Schema::hasColumn('resource_requests', 'responsible_center')) {
                $table->string('responsible_center')->nullable()->after('ris_no');
            }
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'funding_source')) {
                $table->string('funding_source')->nullable()->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('planting_date');
        });
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropColumn(['ris_no', 'responsible_center']);
        });
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });
    }
};
