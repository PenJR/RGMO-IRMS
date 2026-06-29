<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'has_expiry')) {
                $table->boolean('has_expiry')->default(false)->after('planting_date');
            }

            if (! Schema::hasColumn('inventory_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('has_expiry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }

            if (Schema::hasColumn('inventory_items', 'has_expiry')) {
                $table->dropColumn('has_expiry');
            }
        });
    }
};
