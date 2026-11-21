<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('year_end_inventory_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('fiscal_year')->unique();
            // ISO dates preserve the full supported fiscal-year range on every driver.
            $table->char('as_of', 10);
            $table->dateTime('captured_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('generator_name');
            $table->text('reason');
            $table->string('reference', 100);
            $table->json('currency');
            $table->unsignedInteger('total_items');
            $table->unsignedInteger('low_stock_items');
            $table->unsignedInteger('missing_price_count');
            $table->string('total_value', 100);
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_end_inventory_snapshots');
    }
};
