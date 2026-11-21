<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_api_usages', function (Blueprint $table) {
            $table->id();
            $table->date('usage_month');
            $table->string('provider', 40);
            $table->unsignedInteger('requests')->default(0);
            $table->timestamp('last_requested_at')->nullable();
            $table->timestamps();
            $table->unique(['usage_month', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_api_usages');
    }
};
