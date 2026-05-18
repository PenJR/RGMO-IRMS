<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('resource_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
