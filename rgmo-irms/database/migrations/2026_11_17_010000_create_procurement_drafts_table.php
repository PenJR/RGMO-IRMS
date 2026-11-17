<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status')->default('active');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->foreignId('completed_procurement_id')->nullable()->unique()->constrained('procurements')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_drafts');
    }
};
