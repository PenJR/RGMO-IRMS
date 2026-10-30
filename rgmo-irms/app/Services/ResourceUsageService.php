<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\User;
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
            if ($release->status !== ResourceRequest::STATUS_COMPLETED || ! $release->fulfilled_at || ! $release->tracks_utilization) {
                throw ValidationException::withMessages(['request_item_id' => 'Choose an eligible completed withdrawal. Historical releases cannot be recorded again.']);
            }
            if ($data['used_on'] < $release->fulfilled_at->toDateString()) {
                throw ValidationException::withMessages(['used_on' => 'Usage cannot be dated before the resource was released.']);
            }
            $used = ResourceUsage::where('request_item_id', $line->id)->sum('quantity');
            if ($data['quantity'] > $line->quantity - $used) {
                throw ValidationException::withMessages(['quantity' => 'Quantity exceeds the unused amount from this withdrawal.']);
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
