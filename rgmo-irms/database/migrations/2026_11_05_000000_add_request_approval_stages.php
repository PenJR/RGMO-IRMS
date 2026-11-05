<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->foreignId('storekeeper_checked_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('storekeeper_checked_at')->nullable()->after('storekeeper_checked_by');
            $table->foreignId('unit_head_approved_by')->nullable()->after('storekeeper_checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('unit_head_approved_at')->nullable()->after('unit_head_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storekeeper_checked_by');
            $table->dropColumn('storekeeper_checked_at');
            $table->dropConstrainedForeignId('unit_head_approved_by');
            $table->dropColumn('unit_head_approved_at');
        });
    }
};
