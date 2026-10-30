<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('record-utilization') ?? false;
    }

    public function rules(): array
    {
        return [
            'request_item_id' => 'required|integer|exists:request_items,id',
            'quantity' => 'required|integer|min:1|max:2147483647',
            'used_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'activity' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'user_id' => 'prohibited',
            'inventory_item_id' => 'prohibited',
            'project_id' => 'prohibited',
        ];
    }
}
