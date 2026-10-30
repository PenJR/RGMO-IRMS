<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_requests', function (Blueprint $table) {
            // Existing releases retain their historical usage interpretation.
            $table->boolean('tracks_utilization')->default(false);
        });
        Schema::table('resource_usages', function (Blueprint $table) {
            $table->foreignId('request_item_id')->nullable()->constrained('request_items')->restrictOnDelete();
            $table->date('used_on')->nullable();
            $table->string('activity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('resource_usages', function (Blueprint $table) {
            $table->dropForeign(['request_item_id']);
            $table->dropColumn(['request_item_id', 'used_on', 'activity']);
        });
        Schema::table('resource_requests', function (Blueprint $table) {
            $table->dropColumn('tracks_utilization');
        });
    }
};
