<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Models\WithdrawalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceUsageService
{
    public function record(array $data, User $actor): ResourceUsage
    {
        abort_unless($actor->hasPermission('record-utilization'), 403);

        return DB::transaction(function () use ($data, $actor) {
            $line = RequestItem::findOrFail($data['request_item_id']);
            // Serialize entries for a release so simultaneous submissions cannot exceed it.
            $release = ResourceRequest::whereKey($line->resource_request_id)->lockForUpdate()->firstOrFail();
            if (! in_array($release->status, [ResourceRequest::STATUS_PARTIALLY_WITHDRAWN, ResourceRequest::STATUS_COMPLETED], true) || ! $release->tracks_utilization) {
                throw ValidationException::withMessages(['request_item_id' => 'Choose an eligible completed withdrawal. Historical releases cannot be recorded again.']);
            }
            $issued = WithdrawalItem::where('request_item_id', $line->id)
                ->whereHas('withdrawal', fn ($q) => $q->whereDate('withdrawn_on', '<=', $data['used_on']))->sum('quantity');
            if (! $release->withdrawals()->exists() && $release->fulfilled_at && $data['used_on'] >= $release->fulfilled_at->toDateString()) {
                $issued = $line->withdrawn_quantity;
            }
            $used = ResourceUsage::where('request_item_id', $line->id)->sum('quantity');
            if ($data['quantity'] > $issued - $used) {
                throw ValidationException::withMessages(['quantity' => 'Quantity exceeds the unused amount actually withdrawn by the usage date.']);
            }

            $usage = ResourceUsage::create([
                'request_item_id' => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'project_id' => $release->project_id,
                'user_id' => $actor->id,
                'quantity' => $data['quantity'],
                'used_on' => $data['used_on'],
                'activity' => $data['activity'],
                'notes' => $data['notes'] ?? null,
            ]);
            AuditLog::log($actor->id, 'create', 'resource_utilization', ResourceUsage::class, $usage->id, null, $usage->toArray());

            return $usage;
        }, 3);
    }
}
