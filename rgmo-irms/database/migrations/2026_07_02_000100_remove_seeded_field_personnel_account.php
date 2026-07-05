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
            ->where('email', 'field.manager@example.com')
            ->delete();
    }

    /**
     * Reverse the database migration.
     */
    public function down(): void
    {
        //
    }
};
