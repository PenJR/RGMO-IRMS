<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GeneratedReportSnapshot;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GeneratedReportSnapshotService
{
    /**
     * Store only server-generated primitive values. Archived reads never rerun a report.
     */
    public function capture(User $actor, string $reportKey, string $title, array $filters, array $snapshot, ?string $generationKey = null): GeneratedReportSnapshot
    {
        Gate::forUser($actor)->authorize('create', [GeneratedReportSnapshot::class, $reportKey]);
        Validator::make(['generation_key' => $generationKey], ['generation_key' => ['nullable', 'uuid']])->validate();
        $generationKey = $generationKey !== null ? mb_strtolower($generationKey) : null;
        if ($existing = $this->existingGeneration($actor, $generationKey)) {
            return $this->verifyExistingGeneration($existing, $reportKey, $filters);
        }
        Validator::make(['report_key' => $reportKey, 'title' => $title, 'snapshot' => $snapshot], [
            'report_key' => ['required', Rule::in(array_keys(GeneratedReportSnapshot::reportTypes()))],
            'title' => ['required', 'string', 'max:255'],
            'snapshot' => ['required', 'array'],
            'snapshot.schema_version' => ['required', 'integer', Rule::in([GeneratedReportSnapshot::SCHEMA_VERSION])],
            'snapshot.metadata' => ['present', 'array'],
            'snapshot.summary' => ['present', 'array'],
            'snapshot.summary.*.label' => ['required', 'string'],
            'snapshot.summary.*.value' => ['present'],
            'snapshot.sections' => ['required', 'array', 'min:1'],
            'snapshot.sections.*.title' => ['required', 'string'],
            'snapshot.sections.*.columns' => ['required', 'array', 'min:1'],
            'snapshot.sections.*.columns.*.key' => ['required', 'string'],
            'snapshot.sections.*.columns.*.label' => ['required', 'string'],
            'snapshot.sections.*.rows' => ['present', 'array'],
            'snapshot.sections.*.rows.*' => ['array'],
        ])->validate();
        $this->assertPrimitiveData($filters);
        $this->assertPrimitiveData($snapshot);
        $this->validateTableCells($snapshot);

        try {
            return DB::transaction(function () use ($actor, $reportKey, $title, $filters, $snapshot, $generationKey) {
                if ($existing = $this->existingGeneration($actor, $generationKey)) {
                    return $this->verifyExistingGeneration($existing, $reportKey, $filters);
                }
                $saved = GeneratedReportSnapshot::create([
                    'user_id' => $actor->id,
                    'generation_key' => $generationKey,
                    'generator_name' => $actor->name,
                    'report_key' => $reportKey,
                    'title' => $title,
                    'schema_version' => GeneratedReportSnapshot::SCHEMA_VERSION,
                    'filters' => $filters,
                    'snapshot' => $snapshot,
                    'generated_at' => now(),
                ]);
                AuditLog::log($actor->id, 'generate', 'reports', null, null, null, null, [
                    'snapshot_id' => $saved->id,
                    'report_key' => $saved->report_key,
                ]);

                return $saved;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            if ($existing = $this->existingGeneration($actor, $generationKey)) {
                return $this->verifyExistingGeneration($existing, $reportKey, $filters);
            }
            throw $exception;
        }
    }

    private function existingGeneration(User $actor, ?string $generationKey): ?GeneratedReportSnapshot
    {
        return $generationKey === null ? null : GeneratedReportSnapshot::query()
            ->where('user_id', $actor->id)->where('generation_key', $generationKey)->first();
    }

    private function verifyExistingGeneration(GeneratedReportSnapshot $existing, string $reportKey, array $filters): GeneratedReportSnapshot
    {
        if ($existing->report_key !== $reportKey || $this->canonicalFilters($existing->filters) !== $this->canonicalFilters($filters)) {
            throw ValidationException::withMessages(['generation_key' => 'This generation request was already used for different report filters. Reload the form and generate a new snapshot.']);
        }

        return $existing;
    }

    private function canonicalFilters(array $filters): string
    {
        ksort($filters);

        return json_encode($filters, JSON_THROW_ON_ERROR);
    }

    private function assertPrimitiveData(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->assertPrimitiveData($child);
            }

            return;
        }
        if ($value === null || is_string($value) || is_int($value) || is_bool($value) || (is_float($value) && is_finite($value))) {
            return;
        }

        throw ValidationException::withMessages(['snapshot' => 'A report snapshot must contain only saved text, numbers, booleans, and null values.']);
    }

    private function validateTableCells(array $snapshot): void
    {
        foreach ($snapshot['summary'] as $summary) {
            if (is_array($summary['value'])) {
                throw ValidationException::withMessages(['snapshot' => 'Summary values must be plain values.']);
            }
        }
        foreach ($snapshot['sections'] as $section) {
            $keys = array_column($section['columns'], 'key');
            if (count($keys) !== count(array_unique($keys))) {
                throw ValidationException::withMessages(['snapshot' => 'Report columns must have unique keys.']);
            }
            foreach ($section['rows'] as $row) {
                foreach ($keys as $key) {
                    if (! array_key_exists($key, $row) || is_array($row[$key])) {
                        throw ValidationException::withMessages(['snapshot' => 'Every report row must contain a plain value for every column.']);
                    }
                }
            }
        }
    }
}
