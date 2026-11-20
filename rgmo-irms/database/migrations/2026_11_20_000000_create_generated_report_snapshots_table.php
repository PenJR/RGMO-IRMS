<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_report_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('generation_key')->nullable();
            $table->string('generator_name');
            $table->string('report_key', 80);
            $table->string('title');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('filters');
            $table->json('snapshot');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['report_key', 'generated_at']);
            $table->index('generated_at');
            $table->unique(['user_id', 'generation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_report_snapshots');
    }
};
