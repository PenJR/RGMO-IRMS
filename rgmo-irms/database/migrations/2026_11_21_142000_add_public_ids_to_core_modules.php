<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = ['projects', 'resource_requests', 'procurements', 'deliveries', 'withdrawals', 'suppliers'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('public_id', 26)->nullable()->unique()->after('id');
            });
            DB::table($name)->whereNull('public_id')->orderBy('id')->each(function ($row) use ($name) {
                DB::table($name)->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};
