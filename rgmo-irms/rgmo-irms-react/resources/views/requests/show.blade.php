@extends('layouts.app')

@section('title', 'Request Details')
@section('page_title', 'Resource Request Approval')

@section('content')
<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">Request #RQ-2024-001</h6>
                    <span class="badge rounded-pill bg-warning-subtle text-dark fw-bold px-3 py-1" style="font-size: 10px;">AWAITING HEAD APPROVAL</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 mb-5">
                    <div class="col-sm-6">
                        <p class="stat-label">Requested By</p>
                        <p class="fw-bold text-dark mb-0">Prof. Maria Santos</p>
                        <p class="text-muted small">College of Agriculture</p>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <p class="stat-label">Submission Date</p>
                        <p class="fw-bold text-dark mb-0">October 20, 2024</p>
                        <p class="text-muted small">09:15 AM</p>
                    </div>
                </div>

                <div class="mb-5">
                    <h6 class="stat-label border-bottom pb-2">Purpose and Scope</h6>
                    <p class="text-dark" style="font-size: 0.875rem; line-height: 1.6;">For the maintenance and fertilization of Field Plot B (Corn Experimental Area). Needed for the third-quarter planting season to ensure optimal crop yield and experimental integrity.</p>
                </div>

                <div>
                    <h6 class="stat-label border-bottom pb-2 text-uppercase">Allocated Resources</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="bg-light">
                                <tr class="text-muted fw-bold" style="font-size: 10px;">
                                    <th class="ps-3 py-2">Item Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-3">Availability</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.8125rem;">
                                <tr>
                                    <td class="ps-3">Urea Fertilizer (50kg Bag)</td>
                                    <td class="text-center fw-bold">3</td>
                                    <td class="text-end pe-3"><span class="text-danger fw-bold">5 Left</span></td>
                                </tr>
                                <tr>
                                    <td class="ps-3">Insecticide (1L Bottle)</td>
                                    <td class="text-center fw-bold">2</td>
                                    <td class="text-end pe-3"><span class="text-success">15 Left</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Sidebar -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Decision Panel</h6>
            </div>
            <div class="card-body p-4">
                <form>
                    <div class="mb-4">
                        <label class="form-label stat-label text-dark">Remarks / Instructions</label>
                        <textarea class="form-control form-control-sm border-light-subtle bg-light" rows="4" placeholder="Add specific instructions for the staff..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-cmu py-2 text-uppercase fw-bold" style="font-size: 10px;">
                            <i data-lucide="check-circle" class="me-2 d-inline-block" style="width: 14px"></i> Approve Request
                        </button>
                        <button type="button" class="btn btn-outline-danger py-2 text-uppercase fw-bold" style="font-size: 10px;">
                            <i data-lucide="x-circle" class="me-2 d-inline-block" style="width: 14px"></i> Reject Request
                        </button>
                        <button type="button" class="btn btn-light border py-2 text-uppercase fw-bold text-muted" style="font-size: 10px;">
                            Defer Action
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Request Timeline</h6>
            </div>
            <div class="card-body p-4">
                <div class="space-y-4">
                    <div class="d-flex gap-3 mb-4">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success-subtle text-success p-2">
                                <i data-lucide="plus" style="width: 14px; height: 14px;"></i>
                            </div>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold" style="font-size: 0.8125rem;">Request Created</p>
                            <p class="mb-0 text-muted" style="font-size: 10px;">By Maria Santos • Oct 20, 09:15 AM</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info-subtle text-info p-2">
                                <i data-lucide="user-check" style="width: 14px; height: 14px;"></i>
                            </div>
                        </div>
                        <div>
                            <p class="mb-0 fw-bold" style="font-size: 0.8125rem;">Admin Verified</p>
                            <p class="mb-0 text-muted" style="font-size: 10px;">By Office Admin • Oct 20, 11:30 AM</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@endsection
