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
        return $this->where('public_id', $value)->orWhere($this->getKeyName(), $value)->first();
    }
}
