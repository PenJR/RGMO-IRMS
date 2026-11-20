<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedInteger('correction_revision')->default(0);
            $table->json('original_data')->nullable();
            $table->timestamp('voided_at')->nullable()->index();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('delivery_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->string('operation', 20);
            $table->text('reason');
            $table->string('incident_report_reference', 100);
            $table->uuid('idempotency_key')->unique();
            $table->string('request_fingerprint', 64);
            $table->unsignedInteger('revision_before');
            $table->unsignedInteger('revision_after');
            $table->json('before_data');
            $table->json('after_data');
            $table->integer('stock_delta');
            $table->foreignId('inventory_transaction_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['delivery_id', 'created_at']);
            $table->unique(['delivery_id', 'revision_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_corrections');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropIndex(['voided_at']);
            $table->dropColumn(['correction_revision', 'original_data', 'voided_at']);
        });
    }
};
