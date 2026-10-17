<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->timestamp('fulfilled_at')->nullable()->after('cancelled_at')->index();
        });

        DB::table('resource_requests')
            ->where('status', 'completed')
            ->whereNull('fulfilled_at')
            ->update(['fulfilled_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropColumn('fulfilled_at');
        });
    }
};
