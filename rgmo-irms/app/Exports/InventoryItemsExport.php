<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryItemsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * Create a new instance.
     */
    public function __construct(protected Builder $items, protected string $currencyCode = 'PHP') {}

    /**
     * Handle collection.
     */
    public function query(): Builder
    {
        return $this->items;
    }

    /**
     * Map one inventory item into an exported spreadsheet row.
     */
    public function map($item): array
    {
        return [
            $item->category->name ?? 'Uncategorized',
            $item->name,
            $item->sku,
            $item->stock,
            $item->min_stock,
            $item->reorder_level,
            $item->unit,
            $item->price,
            $item->description,
            $item->getStockStatus(),
        ];
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
