<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 fw-bold mb-0">My Dashboard</h2>
            <p class="text-muted mb-0 small">Track your resource requests and take the next action.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4 dashboard-page staff-dashboard">
        <section class="dashboard-welcome" aria-labelledby="staffDashboardWelcomeTitle">
            <div class="dashboard-welcome__content">
                <p class="dashboard-welcome__eyebrow mb-2">{{ now()->format('l, F j') }}</p>
                <h1 id="staffDashboardWelcomeTitle">Welcome back, {{ Str::before(Auth::user()->name, ' ') }}.</h1>
                <p class="mb-0">
                    You have <strong>{{ $stats['pending_requests'] }} pending {{ Str::plural('request', $stats['pending_requests']) }}</strong>
                    and <strong>{{ $stats['approved_requests'] }} approved {{ Str::plural('request', $stats['approved_requests']) }}</strong>.
                </p>
            </div>
            <div class="dashboard-welcome__actions" aria-label="Quick actions">
                <a href="{{ route('requests.create') }}" class="btn dashboard-action-btn dashboard-action-btn--accent">
                    <i data-lucide="file-plus-2" aria-hidden="true"></i>
                    New request
                </a>
                <a href="{{ route('requests.index') }}" class="btn btn-light dashboard-action-btn">
                    <i data-lucide="clipboard-list" aria-hidden="true"></i>
                    View my requests
                </a>
            </div>
        </section>

        <div class="dashboard-section-heading">
            <div>
                <p class="module-eyebrow mb-1">Request overview</p>
                <h2>Your activity at a glance</h2>
            </div>
            <span>Updated {{ now()->format('g:i A') }}</span>
        </div>

        <div class="row g-4">
            <div class="col-12 col-md-4">
                <a href="{{ route('requests.index') }}" class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--inventory dashboard-kpi--interactive text-decoration-none" aria-label="View all {{ $stats['total_requests'] }} of your requests">
                    <div class="card-body">
                        <div class="dashboard-kpi__body">
                            <div class="stat-icon stat-icon--inventory" aria-hidden="true">
                                <i data-lucide="files"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="dashboard-kpi__label">Total Requests</p>
                                <h3 class="mb-0 fw-bold">{{ $stats['total_requests'] }}</h3>
                                <span class="dashboard-kpi__context">{{ $stats['submitted_this_week'] }} submitted this week</span>
                            </div>
                            <i data-lucide="arrow-up-right" class="dashboard-kpi__affordance" aria-hidden="true"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-4">
                <a href="{{ route('requests.index', ['status' => 'pending']) }}" class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--warning dashboard-kpi--interactive text-decoration-none" aria-label="View your {{ $stats['pending_requests'] }} pending requests">
                    <div class="card-body">
                        <div class="dashboard-kpi__body">
                            <div class="stat-icon stat-icon--warning" aria-hidden="true">
                                <i data-lucide="clock-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="dashboard-kpi__label">Pending Requests</p>
                                <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending_requests'] }}</h3>
                                <span class="dashboard-kpi__context">{{ $stats['pending_overdue'] }} past the needed date</span>
                            </div>
                            <i data-lucide="arrow-up-right" class="dashboard-kpi__affordance" aria-hidden="true"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-4">
                <a href="{{ route('requests.index', ['status' => 'approved']) }}" class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--ready dashboard-kpi--interactive text-decoration-none" aria-label="View your {{ $stats['approved_requests'] }} approved requests">
                    <div class="card-body">
                        <div class="dashboard-kpi__body">
                            <div class="stat-icon" aria-hidden="true">
                                <i data-lucide="badge-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="dashboard-kpi__label">Approved Requests</p>
                                <h3 class="mb-0 fw-bold text-success">{{ $stats['approved_requests'] }}</h3>
                                <span class="dashboard-kpi__context">{{ $stats['approved_this_week'] }} approved this week</span>
                            </div>
                            <i data-lucide="arrow-up-right" class="dashboard-kpi__affordance" aria-hidden="true"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="dashboard-section-heading dashboard-section-heading--spaced">
            <div>
                <p class="module-eyebrow mb-1">Recent activity</p>
                <h2>My latest requests</h2>
            </div>
            <a href="{{ route('requests.index') }}" class="btn btn-sm btn-outline-success">View all requests</a>
        </div>

        <div class="card border-0 dashboard-panel">
            @if($myRequests->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 staff-request-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">Purpose</th>
                                <th class="px-4 py-3">Requested</th>
                                <th class="px-4 py-3">Needed by</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-end" data-sortable="false"><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myRequests as $request)
                                @php
                                    $statusClass = match($request->status) {
                                        App\Models\ResourceRequest::STATUS_PENDING => 'status-badge--warning',
                                        App\Models\ResourceRequest::STATUS_APPROVED => 'status-badge--success',
                                        App\Models\ResourceRequest::STATUS_REJECTED => 'status-badge--danger',
                                        default => 'status-badge--info',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="staff-request-icon" aria-hidden="true"><i data-lucide="file-text"></i></span>
                                            <div>
                                                <p class="mb-0 fw-bold text-dark">{{ Str::limit($request->purpose, 58) }}</p>
                                                <span class="small text-muted">Request #{{ $request->id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-muted">{{ $request->created_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-3">
                                        @if($request->needed_date)
                                            <span class="{{ $request->status === App\Models\ResourceRequest::STATUS_PENDING && $request->needed_date->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                {{ $request->needed_date->format('M j, Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="badge status-badge {{ $statusClass }}">{{ str($request->status)->headline() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ route('requests.show', $request) }}" class="btn btn-sm btn-outline-success" aria-label="View request number {{ $request->id }}">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="staff-dashboard__empty text-center">
                    <span class="staff-dashboard__empty-icon" aria-hidden="true"><i data-lucide="clipboard-plus"></i></span>
                    <h3>No requests yet</h3>
                    <p>Create your first resource request and its progress will appear here.</p>
                    <a href="{{ route('requests.create') }}" class="btn btn-cmu">
                        <i data-lucide="plus" aria-hidden="true"></i>
                        Create request
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
