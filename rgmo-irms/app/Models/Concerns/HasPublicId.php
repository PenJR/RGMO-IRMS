<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (blank($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        // During a rolling migration, legacy rows without a generated public ID
        // remain reachable until the backfill has run.
        return filled($this->public_id) ? 'public_id' : 'id';
    }

    /** Accept a numeric key only for pre-migration links; new URLs resolve by ULID. */
    public function resolveRouteBinding($value, $field = null)
    {
        // PostgreSQL evaluates both sides of an OR expression. Comparing a ULID
        // to the numeric primary key therefore raises an invalid bigint error,
        // even when the public ID matches. Select one compatible key instead.
        if (ctype_digit((string) $value)) {
            return $this->where($this->getKeyName(), $value)->first();
        }

        return $this->where('public_id', $value)->first();
    }
}
