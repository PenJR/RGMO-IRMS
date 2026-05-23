<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    // Scopes
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    // Static methods
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function currency(): array
    {
        $currency = static::get('default_currency', ['code' => 'PHP', 'symbol' => '₱']);

        return is_array($currency) ? $currency : ['code' => 'PHP', 'symbol' => '₱'];
    }

    public static function currencyCode(): string
    {
        return static::currency()['code'] ?? 'PHP';
    }

    public static function currencySymbol(): string
    {
        return static::currency()['symbol'] ?? '₱';
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
