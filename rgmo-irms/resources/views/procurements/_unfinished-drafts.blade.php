<div class="card border-0 shadow-sm mb-4" data-po-unfinished>
    <div class="card-body">
        <h3 class="h5">Unfinished entries</h3>
        <p class="small text-muted">Your automatically saved entries are private. Continue one to finish preparing its purchase order, or discard it.</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Purchase</th><th scope="col">Last saved</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody data-po-drafts-body>
                    @forelse($unfinishedDrafts as $unfinished)
                        <tr data-po-draft-row="{{ $unfinished->id }}" data-revision="{{ $unfinished->revision }}">
                            <td data-po-draft-name>{{ $unfinished->payload['po_number'] ?? 'Untitled purchase' }}</td>
                            <td data-po-draft-time>{{ $unfinished->updated_at->format('M d, Y g:i A') }}</td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-po-resume="{{ $unfinished->id }}">Continue</button> <button type="button" class="btn btn-sm btn-outline-danger" data-po-discard="{{ $unfinished->id }}">Discard</button></td>
                        </tr>
                    @empty
                        <tr data-po-drafts-empty><td colspan="3" class="text-center text-muted">No unfinished entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $unfinishedDrafts->links() }}</div>
    </div>
</div>
