<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        foreach (['JF', 'STF'] as $code) {
            DB::table('funding_sources')->insert([
                'code' => $code,
                'name' => $code,
                'description' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['procurements', 'inventory_transactions'] as $table) {
            DB::table($table)->whereNotNull('funding_source')
                ->select('funding_source')->distinct()->orderBy('funding_source')
                ->chunk(500, function ($records) {
                    foreach ($records as $record) {
                        $code = (string) $record->funding_source;
                        if (trim($code) === '' || DB::table('funding_sources')->where('code', $code)->exists()) {
                            continue;
                        }

                        DB::table('funding_sources')->insert([
                            'code' => $code,
                            'name' => $code,
                            'description' => 'Preserved from existing purchase or inventory records.',
                            'status' => 'inactive',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignId('funding_source_id')->nullable()->index()->constrained('funding_sources')->restrictOnDelete();
        });

        DB::table('procurements')->select('id', 'funding_source')->orderBy('id')
            ->chunkById(500, function ($procurements) {
                $fundingSourceIds = DB::table('funding_sources')
                    ->whereIn('code', $procurements->pluck('funding_source')->filter(fn ($code) => $code !== null && trim((string) $code) !== '')->unique())
                    ->pluck('id', 'code');

                foreach ($procurements as $procurement) {
                    $fundingSourceId = $fundingSourceIds->get($procurement->funding_source);
                    if ($fundingSourceId !== null) {
                        DB::table('procurements')->where('id', $procurement->id)
                            ->update(['funding_source_id' => $fundingSourceId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropForeign(['funding_source_id']);
            $table->dropIndex(['funding_source_id']);
            $table->dropColumn('funding_source_id');
        });

        Schema::dropIfExists('funding_sources');
    }
};
