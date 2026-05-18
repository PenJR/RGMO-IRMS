<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">System Backup</h2>
                <p class="text-muted mb-0">Run a manual backup and review recent backup records.</p>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <p class="mb-3">Performing a backup will use the configured backup driver and store the latest system snapshot. Review the results after completion in the audit history section below.</p>
                <form method="POST" action="{{ route('admin.backup.run') }}">
                    @csrf
                    <button type="submit" class="btn btn-cmu">Run Backup Now</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="mb-3">Recent Backup Records</h5>
                @if($backups->count() > 0)
                    <div class="list-group">
                        @foreach($backups as $backup)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">Backup executed</div>
                                    <div class="text-muted small">{{ $backup->created_at?->format('M d, Y H:i') ?? 'Unknown' }}</div>
                                </div>
                                <span class="badge bg-success">Completed</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <p class="text-muted mb-0">No backup history available yet. Run a backup to record system activity here.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
