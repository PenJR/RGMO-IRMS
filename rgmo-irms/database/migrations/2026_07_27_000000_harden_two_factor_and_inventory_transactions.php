<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->change();
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_recovery_codes');
        });

        DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->orderBy('id')
            ->each(function (object $user): void {
                try {
                    Crypt::decryptString($user->two_factor_secret);
                } catch (DecryptException) {
                    DB::table('users')->where('id', $user->id)->update([
                        'two_factor_secret' => Crypt::encryptString($user->two_factor_secret),
                    ]);
                }
            });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('destination');
            $table->string('idempotency_key')->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['reference', 'idempotency_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_recovery_codes', 'two_factor_last_used_step']);
        });

        DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->orderBy('id')
            ->each(function (object $user): void {
                try {
                    DB::table('users')->where('id', $user->id)->update([
                        'two_factor_secret' => Crypt::decryptString($user->two_factor_secret),
                    ]);
                } catch (DecryptException) {
                    // The value is already plaintext.
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_secret')->nullable()->change();
        });
    }
};
