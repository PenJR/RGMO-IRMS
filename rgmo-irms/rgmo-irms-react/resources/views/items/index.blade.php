@extends('layouts.app')

@section('title', 'Manage Inventory')
@section('page_title', 'Inventory Management')

@section('content')
<div class="card border-0 overflow-hidden shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-4 px-4 border-bottom">
        <div>
            <h5 class="mb-1 fw-bold text-dark">Current Stock Inventory</h5>
            <p class="text-muted mb-0" style="font-size: 0.75rem;">Tracking all fertilizers, seeds, tools, and chemicals allocated for CMU operations.</p>
        </div>
        <div>
            <button class="btn btn-light btn-sm text-uppercase fw-bold border me-2" style="font-size: 10px;">
                <i data-lucide="download" class="me-2 d-inline-block" style="width: 12px"></i> Export
            </button>
            <button class="btn btn-cmu btn-sm text-uppercase fw-bold" data-bs-toggle="modal" data-bs-target="#addItemModal" style="font-size: 10px;">
                <i data-lucide="plus" class="me-2 d-inline-block" style="width: 12px"></i> Add New Item
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Filter Bar -->
        <div class="row g-3 p-4 bg-light border-bottom">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width: 14px"></i></span>
                    <input type="text" class="form-control border-start-0" placeholder="Search resources...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm">
                    <option selected>All Categories</option>
                    <option>Fertilizers</option>
                    <option>Seeds</option>
                    <option>Tools</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-gray-50">
                    <tr class="text-uppercase text-muted fw-bold" style="font-size: 10px;">
                        <th class="ps-4 py-3">Item Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Unit</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size: 0.8125rem;">
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">Urea Fertilizer (50kg Bag)</div>
                            <div class="text-muted" style="font-size: 10px;">ID: CHM-001</div>
                        </td>
                        <td><span class="text-muted">Chemicals</span></td>
                        <td><span class="fw-bold text-danger">5</span></td>
                        <td>Bags</td>
                        <td>10</td>
                        <td><span class="px-2 py-0.5 rounded-pill bg-danger-subtle text-danger fw-bold" style="font-size: 10px;">CRITICAL</span></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-link text-muted p-1" title="Edit Item"><i data-lucide="edit-3" style="width: 16px"></i></button>
                            <button class="btn btn-sm btn-link text-muted p-1" title="View History"><i data-lucide="history" style="width: 16px"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">Yellow Corn Seeds (Pioneer)</div>
                            <div class="text-muted" style="font-size: 10px;">ID: SED-024</div>
                        </td>
                        <td><span class="text-muted">Seeds</span></td>
                        <td><span class="fw-bold">450</span></td>
                        <td>Kilos</td>
                        <td>50</td>
                        <td><span class="px-2 py-0.5 rounded-pill bg-green-subtle text-green fw-bold" style="font-size: 10px;">OPTIMAL</span></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-link text-muted p-1" title="Edit Item"><i data-lucide="edit-3" style="width: 16px"></i></button>
                            <button class="btn btn-sm btn-link text-muted p-1" title="View History"><i data-lucide="history" style="width: 16px"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal refinement -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Record New Resource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form>
                    <div class="mb-3">
                        <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px;">Identification</label>
                        <input type="text" class="form-control" placeholder="Item Name (e.g. Hybrid Rice Seeds)">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px;">Classification</label>
                            <select class="form-select">
                                <option>Fertilizers</option>
                                <option>Seeds</option>
                                <option>Tools</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px;">Unit of Measure</label>
                            <input type="text" class="form-control" placeholder="Bags, Kilos, Pcs">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px;">Opening Stock</label>
                            <input type="number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 10px;">Critical Level</label>
                            <input type="number" class="form-control" value="10">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light border w-100 mb-2 py-2 fw-bold text-uppercase" style="font-size: 10px;" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-cmu w-100 py-2 fw-bold text-uppercase" style="font-size: 10px;">Register Resource</button>
            </div>
        </div>
    </div>
</div>
@endsection

@endsection
