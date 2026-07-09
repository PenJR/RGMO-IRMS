<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h5 fw-bold mb-0">Module Health</h2>
                <p class="text-muted mb-0 small">Operational status across inventory, requests, security, and audit activity.</p>
            </div>
            <span class="badge text-uppercase fw-bold px-3 py-2
                @class([
                    'bg-success-subtle text-success' => $health['summary']['overall_status'] === 'healthy',
                    'bg-warning-subtle text-warning' => $health['summary']['overall_status'] === 'warning',
                    'bg-danger-subtle text-danger' => $health['summary']['overall_status'] === 'critical',
                ])">
                {{ $health['summary']['overall_status'] }}
            </span>
        </div>
    </x-slot>

    @php
        $statusStyles = [
            'healthy' => ['label' => 'Healthy', 'class' => 'success', 'icon' => 'check-circle-2'],
            'warning' => ['label' => 'Watch', 'class' => 'warning', 'icon' => 'alert-triangle'],
            'critical' => ['label' => 'Critical', 'class' => 'danger', 'icon' => 'circle-alert'],
        ];
    @endphp

    <div class="container-fluid py-4">
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i data-lucide="check-circle-2" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-bold small mb-1">Healthy Modules</p>
                            <h3 class="fw-bold mb-0">{{ $health['summary']['healthy_modules'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-bold small mb-1">Watch Modules</p>
                            <h3 class="fw-bold mb-0">{{ $health['summary']['warning_modules'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i data-lucide="circle-alert" style="width: 24px; height: 24px;"></i>
                        </div>
                        <div>
                            <p class="text-muted text-uppercase fw-bold small mb-1">Critical Modules</p>
                            <h3 class="fw-bold mb-0">{{ $health['summary']['critical_modules'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            @foreach($health['modules'] as $module)
                @php $style = $statusStyles[$module['status']]; @endphp
                <div class="col-12 col-xl-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-start justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-{{ $style['class'] }}-subtle text-{{ $style['class'] }} d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i data-lucide="{{ $module['icon'] }}" style="width: 24px; height: 24px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1">{{ $module['label'] }}</h5>
                                    <p class="text-muted small mb-0">Updated {{ $health['generated_at'] }}</p>
                                </div>
                            </div>
                            <span class="badge bg-{{ $style['class'] }}-subtle text-{{ $style['class'] }} d-inline-flex align-items-center gap-1">
                                <i data-lucide="{{ $style['icon'] }}" style="width: 14px; height: 14px;"></i>
                                {{ $style['label'] }}
                            </span>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="d-flex align-items-end gap-2 mb-3">
                                <span class="display-6 fw-bold lh-1">{{ $module['headline'] }}</span>
                                <span class="text-muted small mb-1">{{ $module['headline_label'] }}</span>
                            </div>
                            <div class="row g-2 mb-4">
                                @foreach($module['metrics'] as $label => $value)
                                    <div class="col-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <p class="text-muted small mb-1">{{ $label }}</p>
                                            <p class="h5 fw-bold mb-0">{{ $value }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($module['label'] !== 'User Security' || auth()->user()->can('viewAny', App\Models\User::class))
                                <a href="{{ $module['route'] }}" class="btn btn-sm btn-outline-success fw-semibold">
                                    {{ $module['action'] }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i data-lucide="alert-circle" class="text-danger" style="width: 20px;"></i>
                            Urgent Inventory
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @forelse($health['urgent']['low_stock_items'] as $item)
                            <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="fw-semibold mb-0">{{ $item->name }}</p>
                                    <p class="text-muted small mb-0">{{ $item->sku }}</p>
                                </div>
                                <span class="badge bg-danger-subtle text-danger">{{ $item->stock }} / {{ $item->min_stock }} {{ $item->unit }}</span>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i data-lucide="check-circle-2" class="text-success opacity-25 mb-2" style="width: 44px; height: 44px;"></i>
                                <p class="text-muted mb-0 small">No low-stock items right now.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i data-lucide="calendar-clock" class="text-warning" style="width: 20px;"></i>
                            Expiring Soon
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @forelse($health['urgent']['expiring_items'] as $item)
                            <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="fw-semibold mb-0">{{ $item->name }}</p>
                                    <p class="text-muted small mb-0">{{ $item->sku }}</p>
                                </div>
                                <span class="badge bg-warning-subtle text-warning">{{ $item->expiry_date->format('M d, Y') }}</span>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i data-lucide="check-circle-2" class="text-success opacity-25 mb-2" style="width: 44px; height: 44px;"></i>
                                <p class="text-muted mb-0 small">No items expiring in the next 30 days.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i data-lucide="clipboard-list" class="text-primary" style="width: 20px;"></i>
                            Pending Requests
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @forelse($health['urgent']['pending_requests'] as $request)
                            <div class="px-4 py-3 border-top">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <p class="fw-semibold mb-1">{{ $request->purpose }}</p>
                                        <p class="text-muted small mb-0">Requested by {{ $request->user?->name ?? 'Unknown user' }}</p>
                                    </div>
                                    <span class="badge bg-light text-dark border">{{ $request->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i data-lucide="check-circle-2" class="text-success opacity-25 mb-2" style="width: 44px; height: 44px;"></i>
                                <p class="text-muted mb-0 small">No pending requests in the queue.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i data-lucide="activity" class="text-success" style="width: 20px;"></i>
                            Recent Audit Activity
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @forelse($health['urgent']['recent_audit_logs'] as $log)
                            <div class="px-4 py-3 border-top d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <p class="fw-semibold mb-0">{{ str($log->action)->replace('_', ' ')->title() }}</p>
                                    <p class="text-muted small mb-0">{{ str($log->module)->replace('_', ' ')->title() }} by {{ $log->user?->name ?? 'System' }}</p>
                                </div>
                                <span class="text-muted small">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i data-lucide="info" class="text-muted opacity-25 mb-2" style="width: 44px; height: 44px;"></i>
                                <p class="text-muted mb-0 small">No audit activity has been recorded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
