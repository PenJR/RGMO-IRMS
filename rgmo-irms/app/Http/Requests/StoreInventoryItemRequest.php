<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

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
            'category_id' => 'required|integer|min:1|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0|max:2147483647',
            'unit' => 'required|in:'.implode(',', config('inventory.units', [])),
            'min_stock' => 'required|integer|min:0|max:2147483647',
            'reorder_level' => 'nullable|integer|min:0|max:2147483647',
            'price' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'description' => 'nullable|string|max:5000',
            'planting_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
        ];
    }
}
