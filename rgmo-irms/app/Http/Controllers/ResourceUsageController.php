<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResourceUsageRequest;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Services\ResourceUsageService;

class ResourceUsageController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('record-utilization'), 403);
        $lines = RequestItem::with(['item', 'request.user', 'request.project'])
            ->whereHas('request', fn ($query) => $query->where('status', ResourceRequest::STATUS_COMPLETED)
                ->where('tracks_utilization', true)->whereNotNull('fulfilled_at'))
            ->withSum('usages', 'quantity')->orderByDesc('id')->get()
            ->filter(fn ($line) => $line->quantity > ($line->usages_sum_quantity ?? 0));
        $usages = ResourceUsage::with(['item', 'user', 'requestItem.request'])
            ->whereNotNull('request_item_id')->latest()->paginate(20);

        return view('utilization.index', compact('lines', 'usages'));
    }

    public function store(StoreResourceUsageRequest $request, ResourceUsageService $service)
    {
        $service->record($request->validated(), $request->user());

        return redirect()->route('utilization.index')->with('success', 'Resource utilization recorded.');
    }
}
