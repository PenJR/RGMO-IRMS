<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('category', 80);
            $table->string('severity', 20)->default('medium');
            $table->string('summary', 255);
            $table->text('description');
            $table->timestamp('occurred_at');
            $table->string('related_type', 40)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('evidence_paths')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('investigated_at')->nullable();
            $table->text('investigation_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 40)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
            $table->timestamps();
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
