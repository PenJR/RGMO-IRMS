<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_item_delivery_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('released_quantity')->default(0);
            $table->timestamps();

            $table->unique(['request_item_id', 'delivery_id']);
            $table->index('delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_item_delivery_reservations');
    }
};
