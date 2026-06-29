<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Project Management</h2>
                <p class="text-muted mb-0">Monitor projects, assigned managers, and resources currently in use.</p>
            </div>
            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="btn btn-cmu d-inline-flex align-items-center gap-2">
                    <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                    New Project
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Projects' => route('projects.index')]" />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('projects.index') }}" class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Project name, code, or description">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                                    {{ Str::of($status)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 col-lg-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-cmu flex-fill">Filter</button>
                        <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-transparent shadow-none">
            <div class="card-body p-0">
                @if($projects->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Project Managers</th>
                                    <th>Resources In Use</th>
                                    <th>Timeline</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projects as $project)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $project->name }}</div>
                                            <div class="text-muted small">{{ $project->code }}</div>
                                            @if($project->description)
                                                <div class="text-muted small">{{ Str::limit($project->description, 70) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill
                                                @if($project->status === 'active') bg-success text-white
                                                @elseif($project->status === 'on_hold') bg-warning text-dark
                                                @elseif($project->status === 'completed') bg-primary text-white
                                                @else bg-secondary text-white @endif">
                                                {{ $project->status_label }}
                                            </span>
                                        </td>
                                        <td>
                                            @forelse($project->managers as $manager)
                                                <span class="badge rounded-pill bg-light text-dark border me-1 mb-1">{{ $manager->name }}</span>
                                            @empty
                                                <span class="text-muted small">No managers assigned</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $project->resource_usages_count }} usage records</td>
                                        <td class="text-muted small">
                                            {{ $project->start_date?->format('M d, Y') ?? 'No start' }}
                                            -
                                            {{ $project->end_date?->format('M d, Y') ?? 'Open' }}
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-primary">View</a>
                                                @can('update', $project)
                                                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $projects->links() }}
                    </div>
                @else
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-5 text-center">
                            <i data-lucide="folder-kanban" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                            <h5 class="fw-bold">No projects found</h5>
                            <p class="text-muted mb-0">Create a project to start tracking assigned managers and resource usage.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
