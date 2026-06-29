<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isStaff();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|string|max:30',
            'min_stock' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date',
        ];
    }
}
