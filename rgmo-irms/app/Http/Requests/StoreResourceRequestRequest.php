<?php

namespace App\Http\Requests;

use App\Models\ResourceRequest;
use App\Rules\CurrentProject;
use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ResourceRequest::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', new CurrentProject],
            'purpose' => 'required|string|max:1000',
            'remarks' => 'nullable|string|max:1000',
            'ris_no' => 'nullable|string|max:50',
            'responsible_center' => 'nullable|string|max:255',
            'requested_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'needed_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'items' => 'required|array|list|min:1|max:100',
            'items.*' => 'required|array:inventory_item_id,quantity',
            'items.*.inventory_item_id' => 'required|integer|min:1|distinct|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:2147483647',
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
