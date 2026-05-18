<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'purpose' => 'required|string|max:500',
            'remarks' => 'nullable|string|max:1000',
            'requested_date' => 'nullable|date',
            'needed_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'You must select at least one item to request.',
            'items.min' => 'You must select at least one item.',
            'items.*.quantity.required' => 'Quantity is required for all items.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
