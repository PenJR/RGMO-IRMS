<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\Rule;

class InputRules
{
    public static function pagination(): array
    {
        return ['page' => 'nullable|integer|min:1|max:1000000', 'per_page' => 'nullable|integer|between:1,100'];
    }

    public static function dates(string $start = 'start_date', string $end = 'end_date'): array
    {
        return [
            $start => 'nullable|date_format:Y-m-d|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            $end => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31', Rule::when(fn ($input) => filled(data_get($input, $start)), 'after_or_equal:'.$start)],
        ];
    }

    public static function inventoryFilters(): array
    {
        return self::pagination() + [
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:low,warning,good,active,reorder,expired,expiring',
            'sort' => 'nullable|string|in:name,sku,stock,price,expiry_date,created_at',
            'direction' => 'nullable|string|in:asc,desc',
        ];
    }

    public static function requestFilters(): array
    {
        return self::pagination() + self::dates() + [
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,approved,rejected,cancelled,completed',
            'user_id' => 'nullable|integer|exists:users,id',
            'sort' => 'nullable|string|in:id,created_at,needed_date,status',
            'direction' => 'nullable|string|in:asc,desc',
        ];
    }

    public static function projectFilters(): array
    {
        return self::pagination() + [
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', Rule::in(Project::statuses())],
        ];
    }

    public static function userFilters(): array
    {
        return self::pagination() + self::dates('date_from', 'date_to') + [
            'search' => 'nullable|string|max:255',
            'role' => ['nullable', 'string', Rule::in(User::availableRoles())],
            'status' => 'nullable|string|in:active,inactive,suspended',
        ];
    }

    public static function reportFilters(): array
    {
        return self::pagination() + self::dates() + [
            'category_id' => 'nullable|integer|exists:categories,id',
            'low_stock' => 'nullable|boolean',
            'user_id' => 'nullable|integer|exists:users,id',
            'item_id' => 'nullable|integer|exists:inventory_items,id',
            'action' => 'nullable|string|max:255',
            'module' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,approved,rejected,cancelled,completed',
        ];
    }

    public static function reportExport(): array
    {
        $rules = [
            'type' => 'required|string|in:inventory,resource_usage,audit_trail,resource_requests,stock_movement,requests,consumption',
            'filters' => 'nullable|array:category_id,low_stock,user_id,item_id,action,module,status,start_date,end_date',
        ];
        foreach (self::reportFilters() as $key => $rule) {
            if (! in_array($key, ['page', 'per_page', 'start_date', 'end_date'], true)) {
                $rules['filters.'.$key] = $rule;
            }
        }

        return $rules + self::dates('filters.start_date', 'filters.end_date');
    }

    public static function settings(): array
    {
        return [
            'settings' => 'required|array:site_name,low_stock_threshold,default_currency',
            'settings.site_name' => 'sometimes|required|string|max:255',
            'settings.low_stock_threshold' => 'sometimes|required|integer|between:1,2147483647',
            'settings.default_currency' => 'sometimes|required|array:code,symbol',
            'settings.default_currency.code' => 'required_with:settings.default_currency|string|regex:/^[A-Z]{3}$/',
            'settings.default_currency.symbol' => 'required_with:settings.default_currency|string|max:10',
        ];
    }
}
