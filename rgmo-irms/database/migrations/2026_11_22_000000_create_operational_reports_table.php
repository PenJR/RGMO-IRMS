<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_reports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('template', 50)->index();
            $table->string('title');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rows');
            $table->string('prepared_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('noted_by')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_reports');
    }
};
