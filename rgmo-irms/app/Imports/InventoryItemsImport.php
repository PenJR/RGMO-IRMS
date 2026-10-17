<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class InventoryItemsImport implements OnEachRow, SkipsEmptyRows, SkipsOnFailure, WithHeadingRow, WithValidation
{   
    use Importable, SkipsFailures;

    private int $created = 0;

    private int $updated = 0;

    private int $restored = 0;

    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Handle model.
     */
    public function onRow(Row $row): void
    {
        $values = $row->toArray();
        $sku = trim($values['sku'] ?? '');

        if ($sku === '') {
            return;
        }

        $category = null;

        if (! empty($values['category_id'])) {
            $category = Category::find($values['category_id']);
        }

        if (! $category && ! empty($values['category'])) {
            $category = Category::firstOrCreate(['name' => trim($values['category'])]);
        }

        $itemData = [
            'category_id' => $category?->id,
            'name' => trim($values['name'] ?? ''),
            'sku' => $sku,
            'stock' => isset($values['stock']) ? (int) $values['stock'] : 0,
            'unit' => trim($values['unit'] ?? 'pcs'),
            'min_stock' => isset($values['min_stock']) ? (int) $values['min_stock'] : 0,
            'reorder_level' => isset($values['reorder_level']) ? (int) $values['reorder_level'] : null,
            'price' => isset($values['price']) ? (float) $values['price'] : null,
            'description' => trim($values['description'] ?? ''),
        ];

        $item = InventoryItem::withTrashed()->where('sku', $itemData['sku'])->first();

        if ($item) {
            if ($item->trashed()) {
                $item->restore();
                $this->restored++;
            }

            $this->inventoryService->updateItem($item, $itemData, auth()->id());
            $this->updated++;
        } else {
            $this->inventoryService->createItem($itemData, auth()->id());
            $this->created++;
        }
    }

    /**
     * Return counts suitable for a user-facing import result summary.
     */
    public function summary(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'restored' => $this->restored,
            'skipped' => $this->failures()->count(),
        ];
    }

    /**
     * Return a bounded list of actionable row validation messages.
     */
    public function failureMessages(int $limit = 20): array
    {
        return $this->failures()
            ->take($limit)
            ->map(fn ($failure) => 'Row '.$failure->row().': '.implode(' ', $failure->errors()))
            ->values()
            ->all();
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
