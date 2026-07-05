<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! is_string($value)) {
                    return $value;
                }

                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    // Scopes
    /**
     * Scope a query to retrieve a specific setting by its key identifier.
     *
     * @param Builder $query
     * @param string $key
     * @return Builder
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    // Static methods
    /**
     * Retrieve a setting value by key, returning a default if not found.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Get the default currency configuration.
     *
     * @return array
     */
    public static function currency(): array
    {
        $currency = static::get('default_currency', ['code' => 'PHP', 'symbol' => '₱']);

        return is_array($currency) ? $currency : ['code' => 'PHP', 'symbol' => '₱'];
    }

    /**
     * Get the active currency code (e.g., PHP).
     *
     * @return string
     */
    public static function currencyCode(): string
    {
        return static::currency()['code'] ?? 'PHP';
    }

    /**
     * Get the active currency symbol (e.g., ₱).
     *
     * @return string
     */
    public static function currencySymbol(): string
    {
        return static::currency()['symbol'] ?? '₱';
    }

    /**
     * Handle set.
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
