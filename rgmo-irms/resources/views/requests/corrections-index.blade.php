<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Correction Proposals</h2>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <x-breadcrumb :items="['Requests' => route('requests.index'), 'Correction Proposals' => route('requests.corrections.index')]" compact />
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @forelse($corrections as $correction)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <h5 class="mb-1">
                                <a href="{{ route('requests.show', $correction->request) }}">RQ-{{ $correction->resource_request_id }}</a>
                                — {{ $correction->request->user?->name ?? 'Unknown requester' }}
                            </h5>
                            <span class="text-muted small">{{ $correction->created_at?->format('M d, Y H:i') }}</span>
                        </div>
                        <p class="mb-1"><strong>Proposal by:</strong> {{ $correction->requester?->name ?? 'Former user' }}</p>
                        <p class="mb-1"><strong>Reason:</strong> {{ $correction->reason }}</p>
                        <p class="mb-0"><strong>Proposed purpose:</strong> {{ $correction->proposed_values['purpose'] }}</p>
                    </div>
                @empty
                    <div class="text-center py-5">
                        <h5>No correction proposals awaiting review</h5>
                        <p class="text-muted mb-0">Proposals submitted by auditors or administrators will appear here.</p>
                    </div>
                @endforelse
                <x-table-pagination :paginator="$corrections" label="correction proposals" />
            </div>
        </div>
    </div>
</x-app-layout>
