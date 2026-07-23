<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryItemsExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    /**
     * Create a new instance.
     */
    public function __construct(protected Collection $items, protected string $currencyCode = 'PHP') {}

    /**
     * Handle collection.
     */
    public function collection(): Collection
    {
        return $this->items->map(function ($item) {
            return [
                'Category' => $item->category->name ?? 'Uncategorized',
                'Name' => $item->name,
                'SKU' => $item->sku,
                'Stock' => $item->stock,
                'Min Stock' => $item->min_stock,
                'Reorder Level' => $item->reorder_level,
                'Unit' => $item->unit,
                "Price ({$this->currencyCode})" => $item->price,
                'Description' => $item->description,
                'Status' => $item->getStockStatus(),
            ];
        });
    }

    /**
     * Handle headings.
     */
    public function headings(): array
    {
        return [
            'Category',
            'Name',
            'SKU',
            'Stock',
            'Min Stock',
            'Reorder Level',
            'Unit',
            "Price ({$this->currencyCode})",
            'Description',
            'Status',
        ];
    }
}
