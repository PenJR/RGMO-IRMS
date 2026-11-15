<?php

use App\Models\InventoryItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('public_id', 26)->nullable()->unique()->after('id');
        });

        InventoryItem::withTrashed()->whereNull('public_id')->orderBy('id')->each(function (InventoryItem $item) {
            $item->forceFill(['public_id' => (string) Str::ulid()])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
