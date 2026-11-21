<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->timestamp('recipient_acknowledged_at')->nullable()->after('notes');
            $table->json('recipient_acknowledgment_snapshot')->nullable()->after('recipient_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['recipient_acknowledged_at', 'recipient_acknowledgment_snapshot']);
        });
    }
};
