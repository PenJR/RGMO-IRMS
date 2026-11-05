<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectInventoryService
{
    public function balances(Project $project, User $viewer): Collection
    {
        return RequestItem::query()
            ->whereHas('request', fn ($query) => $query
                ->where('project_id', $project->id)
                ->whereIn('status', ['approved', 'partially_withdrawn', 'completed'])
                ->when($viewer->isProjectManager(), fn ($query) => $query->where('user_id', $viewer->id)))
            ->with('item.category')
            ->get()
            ->groupBy('inventory_item_id')
            ->map(function (Collection $lines) {
                return [
                    'item' => $lines->first()->item,
                    'remaining' => $lines->sum('remaining_quantity'),
                    'withdrawn' => $lines->sum('withdrawn_quantity'),
                ];
            })
            ->sortBy(fn ($row) => $row['item']?->name)
            ->values();
    }
}
