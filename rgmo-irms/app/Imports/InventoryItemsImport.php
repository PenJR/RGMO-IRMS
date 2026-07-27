<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class InventoryItemsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Handle model.
     */
    public function model(array $row)
    {
        $sku = trim($row['sku'] ?? '');

        if ($sku === '') {
            return null;
        }

        $category = null;

        if (! empty($row['category_id'])) {
            $category = Category::find($row['category_id']);
        }

        if (! $category && ! empty($row['category'])) {
            $category = Category::firstOrCreate(['name' => trim($row['category'])]);
        }

        $itemData = [
            'category_id' => $category?->id,
            'name' => trim($row['name'] ?? ''),
            'sku' => $sku,
            'stock' => isset($row['stock']) ? (int) $row['stock'] : 0,
            'unit' => trim($row['unit'] ?? 'pcs'),
            'min_stock' => isset($row['min_stock']) ? (int) $row['min_stock'] : 0,
            'reorder_level' => isset($row['reorder_level']) ? (int) $row['reorder_level'] : null,
            'price' => isset($row['price']) ? (float) $row['price'] : null,
            'description' => trim($row['description'] ?? ''),
        ];

        $item = InventoryItem::withTrashed()->where('sku', $itemData['sku'])->first();

        if ($item) {
            if ($item->trashed()) {
                $item->restore();
            }

            $item = $this->inventoryService->updateItem($item, $itemData, auth()->id());
        } else {
            $item = $this->inventoryService->createItem($itemData, auth()->id());
        }

        return $item;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:255', 'required_without:category_id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}
