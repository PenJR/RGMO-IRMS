<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_snapshot_explanations', function (Blueprint $table) {
            $table->uuid('snapshot_id')->primary();
            $table->foreign('snapshot_id')->references('id')->on('generated_report_snapshots')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('generator_name');
            $table->string('provider', 50);
            $table->string('model');
            $table->json('explanation');
            $table->timestamp('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_snapshot_explanations');
    }
};
