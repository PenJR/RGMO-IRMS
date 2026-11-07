<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'category_id' => 'nullable|required_without:category_name|prohibits:category_name|integer|min:1|exists:categories,id,deleted_at,NULL',
            'category_name' => 'nullable|required_without:category_id|string|max:255',
            'name' => 'required|string|max:255',
            'sku' => 'sometimes|required|string|max:255|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0|max:2147483647',
            'unit' => 'required|in:'.implode(',', config('inventory.units', [])),
            'min_stock' => 'required|integer|min:0|max:2147483647',
            'reorder_level' => 'nullable|integer|min:0|max:2147483647',
            'price' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'description' => 'nullable|string|max:5000',
            'planting_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'has_expiry' => 'sometimes|boolean',
            'track_batches' => 'sometimes|boolean',
            'batch_number' => [Rule::requiredIf(fn () => ($this->boolean('track_batches') || $this->boolean('has_expiry')) && (int) $this->input('stock', 0) > 0), 'nullable', 'string', 'max:100'],
            'expiry_date' => [Rule::requiredIf(fn () => $this->boolean('has_expiry') && (int) $this->input('stock', 0) > 0), 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
        ];
    }
}
