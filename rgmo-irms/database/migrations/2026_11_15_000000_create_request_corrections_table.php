<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('proposed_values');
            $table->json('original_values')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['resource_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_corrections');
    }
};
