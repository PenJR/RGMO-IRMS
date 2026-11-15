<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IncidentReport extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const CATEGORIES = [
        'damaged_goods' => 'Damaged goods',
        'expired_goods' => 'Expired goods',
        'lost_stock' => 'Lost or missing stock',
        'stock_discrepancy' => 'Stock discrepancy or shortage',
        'supplier_delivery' => 'Supplier or delivery issue',
        'equipment_facility' => 'Equipment or facility issue',
        'other' => 'Other',
    ];

    public const RELATED_TYPES = [
        'inventory_item' => ['label' => 'Inventory item', 'table' => 'inventory_items'],
        'inventory_batch' => ['label' => 'Inventory batch', 'table' => 'inventory_batches'],
        'procurement' => ['label' => 'Procurement', 'table' => 'procurements'],
        'delivery' => ['label' => 'Delivery', 'table' => 'deliveries'],
        'resource_request' => ['label' => 'Resource request', 'table' => 'resource_requests'],
        'withdrawal' => ['label' => 'Withdrawal', 'table' => 'withdrawals'],
        'project' => ['label' => 'Project', 'table' => 'projects'],
        'supplier' => ['label' => 'Supplier', 'table' => 'suppliers'],
    ];

    protected $fillable = [
        'category',
        'severity',
        'summary',
        'description',
        'occurred_at',
        'related_type',
        'related_id',
        'evidence_paths',
        'status',
        'reported_by',
        'investigated_by',
        'investigated_at',
        'investigation_notes',
        'resolved_by',
        'resolved_at',
        'resolution',
        'resolution_notes',
        'inventory_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'evidence_paths' => 'array',
            'investigated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigated_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_INVESTIGATING]);
    }

    public function reference(): string
    {
        return 'IR-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function relatedRecordLabel(): ?string
    {
        if (! $this->related_type || ! $this->related_id || ! isset(self::RELATED_TYPES[$this->related_type])) {
            return null;
        }

        $record = match ($this->related_type) {
            'inventory_item' => InventoryItem::withTrashed()->find($this->related_id),
            'inventory_batch' => InventoryBatch::with('inventoryItem')->find($this->related_id),
            'procurement' => Procurement::find($this->related_id),
            'delivery' => Delivery::with('inventoryItem')->find($this->related_id),
            'resource_request' => ResourceRequest::withTrashed()->find($this->related_id),
            'withdrawal' => Withdrawal::find($this->related_id),
            'project' => Project::withTrashed()->find($this->related_id),
            'supplier' => Supplier::find($this->related_id),
            default => null,
        };
        $name = match ($this->related_type) {
            'inventory_item' => $record?->name,
            'inventory_batch' => $record ? $record->inventoryItem?->name.' — '.$record->display_name : null,
            'procurement' => $record?->po_number,
            'delivery' => $record ? $record->inventoryItem?->name.' — delivery #'.$record->id : null,
            'resource_request' => $record ? 'RQ-'.$record->id : null,
            'withdrawal' => $record ? 'WD-'.$record->id : null,
            'project' => $record?->name,
            'supplier' => $record?->company_name,
            default => null,
        };

        return self::RELATED_TYPES[$this->related_type]['label'].' #'.$this->related_id.($name ? ' — '.$name : '');
    }

    public function evidenceExists(string $path): bool
    {
        return in_array($path, $this->evidence_paths ?? [], true) && Storage::disk('local')->exists($path);
    }
}
