<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Incident reports</h2></x-slot>
    <div class="container-fluid py-4">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="d-flex justify-content-end mb-3">
            @if($canReport)<a href="{{ route('incidents.create') }}" class="btn btn-cmu">Report an incident</a>@endif
        </div>
        <div class="card"><div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Reference</th><th>Incident</th><th>Reported</th><th>Reporter</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($incidents as $incident)
                    <tr>
                        <td>{{ $incident->reference() }}</td>
                        <td><div class="fw-semibold">{{ $incident->summary }}</div><small class="text-muted">{{ \App\Models\IncidentReport::CATEGORIES[$incident->category] ?? $incident->category }} · {{ ucfirst($incident->severity) }}</small></td>
                        <td>{{ $incident->created_at->format('M j, Y') }}</td>
                        <td>{{ $incident->reporter?->name ?? 'Former user' }}</td>
                        <td><span class="badge text-bg-{{ $incident->status === 'resolved' ? 'success' : ($incident->status === 'closed' ? 'secondary' : 'warning') }}">{{ str($incident->status)->replace('_', ' ')->title() }}</span></td>
                        <td><a href="{{ route('incidents.show', $incident) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-5">No incident reports found.</td></tr>@endforelse
                </tbody>
            </table>
        </div><div class="card-body">{{ $incidents->links() }}</div></div>
    </div>
</x-app-layout>
