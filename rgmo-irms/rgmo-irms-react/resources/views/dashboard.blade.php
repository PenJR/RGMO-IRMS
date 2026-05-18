@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Office Dashboard')

@section('content')
<div class="row g-4 mb-5">
    <!-- Stats Widgets -->
    <div class="col-md-3">
        <div class="card h-100 p-4">
            <p class="stat-label">Total Stock Items</p>
            <p class="stat-value text-cmu-green">1,284</p>
            <div class="mt-2 text-success" style="font-size: 10px;">
                <i data-lucide="trending-up" class="d-inline-block me-1" style="width: 12px"></i>
                12% from last month
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 p-4">
            <p class="stat-label">Pending Approvals</p>
            <p class="stat-value text-cmu-gold" style="color: #FFCC00 !important;">18</p>
            <p class="mt-2 text-muted" style="font-size: 10px;">Awaiting Head signature</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 p-4">
            <p class="stat-label">Low Stock Alerts</p>
            <p class="stat-value text-danger">4</p>
            <p class="mt-2 text-muted" style="font-size: 10px;">Critical items needing reorder</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 p-4">
            <p class="stat-label">Active Requests</p>
            <p class="stat-value text-primary">22</p>
            <p class="mt-2 text-muted" style="font-size: 10px;">In progress / withdrawal phase</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Tables Row -->
    <div class="col-md-8">
        <div class="card h-100 overflow-hidden">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <h6 class="mb-0 fw-bold">Critical Inventory Status</h6>
                <a href="#" class="text-uppercase text-cmu-green fw-bold" style="font-size: 10px; text-decoration: none;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-uppercase text-muted" style="font-size: 10px;">
                            <th class="ps-4 py-3">Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th class="text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.8125rem;">
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold">NPK Fertilizer (14-14-14)</div>
                            </td>
                            <td>Chemicals</td>
                            <td>12 Bags</td>
                            <td class="text-end pe-4">
                                <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-1 fw-bold" style="font-size: 10px;">LOW</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold">Corn Hybrid Seeds (CMU-V1)</div>
                            </td>
                            <td>Seeds</td>
                            <td>450 Kilos</td>
                            <td class="text-end pe-4">
                                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-1 fw-bold" style="font-size: 100px;">NORMAL</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold">Bolo / Hand Machete</div>
                            </td>
                            <td>Tools</td>
                            <td>8 Units</td>
                            <td class="text-end pe-4">
                                <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-1 fw-bold" style="font-size: 10px;">LOW</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="col-md-4">
        <div class="card h-100 flex-column">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-pill bg-warning" style="width: 4px; height: inherit; min-height: 40px;"></div>
                    <div>
                        <p class="mb-0 fw-bold" style="font-size: 0.8125rem;">Request #REQ-042 Approved</p>
                        <p class="mb-0 text-muted" style="font-size: 10px;">Staff: J. Dela Cruz • 10m ago</p>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-pill bg-cmu-green" style="width: 4px; height: inherit; min-height: 40px;"></div>
                    <div>
                        <p class="mb-0 fw-bold" style="font-size: 0.8125rem;">Stock Withdrawal: Seeds</p>
                        <p class="mb-0 text-muted" style="font-size: 10px;">Item: Rice Seeds P-12 • 1h ago</p>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-4">
                    <div class="rounded-pill bg-danger" style="width: 4px; height: inherit; min-height: 40px;"></div>
                    <div>
                        <p class="mb-0 fw-bold text-danger" style="font-size: 0.8125rem;">New Request Rejected</p>
                        <p class="mb-0 text-muted" style="font-size: 10px;">Reason: Incomplete Docs • 5h ago</p>
                    </div>
                </div>
                
                <button class="btn btn-light w-100 mt-auto border text-uppercase fw-bold text-muted" style="font-size: 10px; padding: 12px;">View Audit Trail</button>
            </div>
        </div>
    </div>
</div>
@endsection
