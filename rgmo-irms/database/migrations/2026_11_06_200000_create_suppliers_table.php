<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->unique();
            $table->string('supplier_code', 80)->unique();
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 80)->nullable();
            $table->string('email')->nullable()->unique();
            $table->text('business_address')->nullable();
            $table->string('tin', 50)->nullable()->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_number')->constrained()->restrictOnDelete();
        });

        if (! Schema::hasTable('procurements')) {
            return;
        }

        $nextCode = 1;
        $supplierIds = [];
        DB::table('procurements')->select('id', 'supplier_name', 'supplier_number')->orderBy('id')->get()->each(function ($procurement) use (&$nextCode, &$supplierIds) {
            $companyName = trim((string) $procurement->supplier_name);
            $contactNumber = trim((string) ($procurement->supplier_number ?? '')) ?: null;
            $key = mb_strtolower($companyName);

            if (! array_key_exists($key, $supplierIds)) {
                $supplierCode = '';
                do {
                    $supplierCode = 'SUP-'.str_pad((string) $nextCode++, 6, '0', STR_PAD_LEFT);
                } while (DB::table('suppliers')->where('supplier_code', $supplierCode)->exists());

                $supplierIds[$key] = DB::table('suppliers')->insertGetId([
                    'company_name' => $companyName !== '' ? $companyName : 'Legacy Supplier '.$nextCode,
                    'supplier_code' => $supplierCode,
                    'contact_person' => null,
                    'contact_number' => $contactNumber,
                    'email' => null,
                    'business_address' => null,
                    'tin' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('procurements')->where('id', $procurement->id)->update(['supplier_id' => $supplierIds[$key]]);
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::dropIfExists('suppliers');
    }
};
