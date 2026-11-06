<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">{{ $project->name }}</h2>
            <p class="text-muted mb-0">{{ $project->code }} · {{ $project->status_label }}</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <x-breadcrumb :items="['Projects' => route('projects.index'), $project->name => route('projects.show', $project)]" compact />
            <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Back to Projects</a>
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-cmu">Edit Project</a>
            @endcan
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-1">Project Inventory</h5>
                <p class="text-muted small mb-0">Resources withdrawn for this project and the balance available to withdraw from its approved requests.</p>
                @if(auth()->user()->isProjectManager())
                    <p class="text-muted small mb-0">Showing resources from your own requests.</p>
                @endif
            </div>
            <div class="card-body p-0">
                @if($projectInventory->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Resource</th>
                                    <th>Unit</th>
                                    <th class="text-end">Available to Withdraw</th>
                                    <th class="text-end">Withdrawn</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectInventory as $balance)
                                    <tr>
                                        <td class="fw-semibold">{{ $balance['item']?->name ?? 'Unknown item' }}</td>
                                        <td>{{ $balance['item']?->unit ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($balance['remaining']) }}</td>
                                        <td class="text-end">{{ number_format($balance['withdrawn']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted px-3 py-2 mb-0">Available to Withdraw = approved quantity minus withdrawn quantity. Pending and rejected requests are excluded. This is the project’s outstanding allocation; actual release still requires sufficient warehouse stock.</p>
                @else
                    <p class="p-4 text-muted mb-0">No approved resources for this project yet. Resources appear here when a request is approved.</p>
                @endif
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Resources Currently Used</h5>
                        <span class="badge rounded-pill bg-light text-dark border">{{ $project->resourceUsages->count() }} usage records</span>
                    </div>
                    <div class="card-body p-0">
                        @if($resourceSummary->count() > 0)
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Resource</th>
                                            <th>Category</th>
                                            <th>Total Quantity Used</th>
                                            <th>Usage Records</th>
                                            <th>Last Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($resourceSummary as $resource)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $resource['item']?->name ?? 'Unknown item' }}</div>

                                                </td>
                                                <td>{{ $resource['item']?->category?->name ?? 'N/A' }}</td>
                                                <td>{{ $resource['quantity'] }} {{ $resource['item']?->unit ?? '' }}</td>
                                                <td>{{ $resource['usage_count'] }}</td>
                                                <td>{{ $resource['last_used_at']?->format('M d, Y H:i') ?? 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-muted">No resource usage has been linked to this project yet.</div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Recent Usage Logs</h5>
                    </div>
                    <div class="card-body p-0">
                        @if($project->resourceUsages->count() > 0)
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Resource</th>
                                            <th>Used By</th>
                                            <th>Field</th>
                                            <th>Quantity</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($project->resourceUsages->sortByDesc('created_at')->take(25) as $usage)
                                            <tr>
                                                <td>{{ $usage->item?->name ?? 'Unknown item' }}</td>
                                                <td>{{ $usage->user?->name ?? 'N/A' }}</td>
                                                <td>{{ $usage->field_id ?? 'N/A' }}</td>
                                                <td>{{ $usage->quantity }} {{ $usage->item?->unit ?? '' }}</td>
                                                <td>{{ $usage->created_at?->format('M d, Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-muted">No usage logs available.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Project Details</h5>
                    </div>
                    <div class="card-body p-4">
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Status</dt>
                            <dd class="col-7">{{ $project->status_label }}</dd>
                            <dt class="col-5 text-muted">Start</dt>
                            <dd class="col-7">{{ $project->start_date?->format('M d, Y') ?? 'Not set' }}</dd>
                            <dt class="col-5 text-muted">End</dt>
                            <dd class="col-7">{{ $project->end_date?->format('M d, Y') ?? 'Open' }}</dd>
                        </dl>
                        @if($project->description)
                            <hr>
                            <p class="mb-0">{{ $project->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Assigned Project Managers</h5>
                    </div>
                    <div class="card-body p-4">
                        @forelse($project->managers as $manager)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-semibold text-muted" style="width: 42px; height: 42px;">
                                    {{ strtoupper(substr($manager->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $manager->name }}</div>
                                    <div class="text-muted small">{{ $manager->email }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No project managers assigned.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
