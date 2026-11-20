<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ForecastSnapshotExplanation;
use App\Models\GeneratedReportSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ForecastSnapshotExplanationService
{
    /** @return array{summary: string, priorities: list<string>, warnings: list<string>}|null */
    public function find(GeneratedReportSnapshot $snapshot): ?array
    {
        return ForecastSnapshotExplanation::find($snapshot->id)?->explanation;
    }

    /** Preserve the first successful brief without changing the numerical snapshot. */
    public function capture(User $actor, GeneratedReportSnapshot $snapshot, array $explanation): array
    {
        Gate::forUser($actor)->authorize('view', $snapshot);
        abort_unless($snapshot->report_key === 'forecast', 422);
        $validated = Validator::make($explanation, [
            'summary' => ['required', 'string'],
            'priorities' => ['present', 'array', 'max:5'],
            'priorities.*' => ['required', 'string'],
            'warnings' => ['present', 'array', 'max:5'],
            'warnings.*' => ['required', 'string'],
        ])->validate();

        return DB::transaction(function () use ($actor, $snapshot, $validated): array {
            // A unique snapshot primary key makes concurrent requests keep the same first result.
            $saved = ForecastSnapshotExplanation::firstOrCreate(['snapshot_id' => $snapshot->id], [
                'user_id' => $actor->id,
                'generator_name' => $actor->name,
                'provider' => 'Gemini',
                'model' => (string) config('services.gemini.model', 'gemini-3.1-flash-lite'),
                'explanation' => $validated,
                'generated_at' => now(),
            ]);

            if ($saved->wasRecentlyCreated) {
                AuditLog::log($actor->id, 'generate', 'reports', null, null, null, null, [
                    'snapshot_id' => $snapshot->id,
                    'report_key' => 'forecast',
                    'artifact' => 'ai-summary',
                ]);
            }

            return $saved->explanation;
        }, 5);
    }
}
