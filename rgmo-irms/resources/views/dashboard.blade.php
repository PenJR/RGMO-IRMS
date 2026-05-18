<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold mb-1">RGMO-IRMS Dashboard</h2>
                <p class="text-muted mb-0">Welcome back, {{ Auth::user()->name }}.</p>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small">Total Users</p>
                                <h3 class="mb-0">{{ $stats['total_users'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                                <i class="bi bi-box-seam fs-4"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small">Inventory Items</p>
                                <h3 class="mb-0">{{ $stats['total_items'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3">
                                <i class="bi bi-exclamation-triangle fs-4"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small">Low Stock Alerts</p>
                                <h3 class="mb-0">{{ $stats['low_stock_count'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3">
                                <i class="bi bi-card-checklist fs-4"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small">Pending Requests</p>
                                <h3 class="mb-0">{{ $stats['pending_requests'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Low Stock Items</h5>
                    </div>
                    <div class="card-body">
                        @if($lowStockItems->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($lowStockItems as $item)
                                    <div class="list-group-item rounded-3 mb-2 border-0 bg-light">
                                        <div class="d-flex justify-content-between align-items-center gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $item->name }}</h6>
                                                <p class="mb-0 text-muted small">SKU: {{ $item->sku }}</p>
                                            </div>
                                            <div class="text-end">
                                                <p class="mb-1 fw-semibold text-danger">{{ $item->stock }} {{ $item->unit }}</p>
                                                <p class="mb-0 text-muted small">Min: {{ $item->min_stock }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center py-4 mb-0">No low stock items</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Recent Requests</h5>
                    </div>
                    <div class="card-body">
                        @if($recentRequests->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($recentRequests as $request)
                                    <div class="list-group-item rounded-3 mb-2 border-0 bg-light">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $request->user->name }}</h6>
                                                <p class="mb-1 text-muted small">{{ $request->purpose }}</p>
                                                <p class="mb-0 text-muted small">{{ $request->created_at->diffForHumans() }}</p>
                                            </div>
                                            <span class="badge rounded-pill
                                                @if($request->status === 'pending') bg-warning text-dark
                                                @elseif($request->status === 'approved') bg-success text-white
                                                @elseif($request->status === 'rejected') bg-danger text-white
                                                @else bg-secondary text-white @endif">
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center py-4 mb-0">No recent requests</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        @if($recentActivities->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($recentActivities as $activity)
                                    <div class="list-group-item rounded-3 mb-2 border-0 bg-light">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <span class="fw-bold">{{ strtoupper(substr($activity->user->name ?? 'S', 0, 1)) }}</span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="mb-1"><span class="fw-semibold">{{ $activity->user->name ?? 'System' }}</span> {{ $activity->action }} in {{ $activity->module }}</p>
                                                <p class="mb-0 text-muted small">{{ $activity->created_at->diffForHumans() }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center py-4 mb-0">No recent activities</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
