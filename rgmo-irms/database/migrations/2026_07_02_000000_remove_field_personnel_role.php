<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the database migration.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'field_personnel')
            ->update(['role' => 'project_manager']);
    }

    /**
     * Reverse the database migration.
     */
    public function down(): void
    {
        //
    }
};
