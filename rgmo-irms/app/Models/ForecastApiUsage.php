<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForecastApiUsage extends Model
{
    protected $fillable = ['usage_month', 'provider', 'requests', 'last_requested_at'];

    protected function casts(): array
    {
        return ['requests' => 'integer', 'last_requested_at' => 'datetime'];
    }
}
