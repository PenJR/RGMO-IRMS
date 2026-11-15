@if($corrections->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="mb-3">Correction history</h5>
            @foreach($corrections as $correction)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <strong>Correction #{{ $correction->id }} — {{ str($correction->status)->headline() }}</strong>
                        <span class="text-muted small">{{ $correction->created_at?->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="mb-1"><strong>Requested by:</strong> {{ $correction->requester?->name ?? 'Former user' }}</p>
                    <p class="mb-2"><strong>Reason:</strong> {{ $correction->reason }}</p>
                    @if($correction->reviewer)
                        <p class="mb-2"><strong>{{ $correction->status === 'applied' ? 'Applied by' : 'Reviewed by' }}:</strong> {{ $correction->reviewer->name }}</p>
                    @endif
                    @if($correction->decision_reason)
                        <p class="mb-2"><strong>Decision:</strong> {{ $correction->decision_reason }}</p>
                    @endif
                    @if($correction->status === 'pending')
                        <dl class="row small mb-3">
                            <dt class="col-sm-3">Proposed purpose</dt><dd class="col-sm-9">{{ $correction->proposed_values['purpose'] }}</dd>
                            <dt class="col-sm-3">Proposed project</dt>
                            <dd class="col-sm-9">{{ $projects->firstWhere('id', $correction->proposed_values['project_id'])?->name ?? 'Project #'.$correction->proposed_values['project_id'] }}</dd>
                            <dt class="col-sm-3">Proposed items</dt>
                            <dd class="col-sm-9">
                                @foreach($correction->proposed_values['items'] as $line)
                                    <div>{{ $correctionItemNames[$line['inventory_item_id']] ?? 'Item #'.$line['inventory_item_id'] }} — {{ $line['quantity'] }}</div>
                                @endforeach
                            </dd>
                        </dl>
                    @endif
                    @if($correction->original_values)
                        <details class="small mb-3">
                            <summary>View preserved original version</summary>
                            <p class="mt-2 mb-1"><strong>Original purpose:</strong> {{ $correction->original_values['purpose'] }}</p>
                            <p class="mb-1"><strong>Original project:</strong> #{{ $correction->original_values['project_id'] }}</p>
                            <p class="mb-1"><strong>Original approval status:</strong> {{ str($correction->original_values['approval']['status'])->headline() }}</p>
                            <p class="mb-1"><strong>Original approval signatures:</strong>
                                Storekeeper #{{ $correction->original_values['approval']['storekeeper_checked_by'] ?? '—' }},
                                Unit Head #{{ $correction->original_values['approval']['unit_head_approved_by'] ?? '—' }},
                                RGMO Head #{{ $correction->original_values['approval']['approved_by'] ?? '—' }}
                            </p>
                            <strong>Original items:</strong>
                            @foreach($correction->original_values['items'] as $line)
                                <div>{{ $correctionItemNames[$line['inventory_item_id']] ?? 'Item #'.$line['inventory_item_id'] }} — {{ $line['quantity'] }}</div>
                            @endforeach
                        </details>
                    @endif
                    @can('manageCorrection', [$request, $correction])
                        <div class="row g-2">
                            <div class="col-md-6">
                                <form action="{{ route('requests.corrections.apply', [$request, $correction]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-cmu w-100" data-confirm="Apply this correction? The old version will be retained and all three approvals must be repeated.">Apply as new revision</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form action="{{ route('requests.corrections.reject', [$request, $correction]) }}" method="POST">
                                    @csrf
                                    <label class="form-label" for="decision-reason-{{ $correction->id }}">Reason for rejection</label>
                                    <textarea id="decision-reason-{{ $correction->id }}" name="decision_reason" class="form-control mb-2" rows="2" maxlength="1000" required></textarea>
                                    <button type="submit" class="btn btn-outline-danger w-100">Reject proposal</button>
                                </form>
                            </div>
                        </div>
                    @endcan
                </div>
            @endforeach
        </div>
    </div>
@endif

@can('requestCorrection', $request)
    <div class="card border-warning shadow-sm mb-4">
        <div class="card-body p-4">
            <h5 class="mb-1">Propose a correction</h5>
            <p class="text-muted small">
                This does not edit the verified document. An administrator must review it, and applying it creates a new revision
                that must pass Storekeeper, Unit Head, and RGMO Head approval again.
            </p>
            <form action="{{ route('requests.corrections.store', $request) }}" method="POST" id="correction-form">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="correction-project" class="form-label">Project</label>
                        <select id="correction-project" name="project_id" class="form-select" required>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id', $request->project_id) === (string) $project->id)>
                                    {{ $project->name }} ({{ $project->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="correction-ris" class="form-label">RIS Number</label>
                        <input id="correction-ris" name="ris_no" value="{{ old('ris_no', $request->ris_no) }}" class="form-control" maxlength="50">
                    </div>
                    <div class="col-md-6">
                        <label for="correction-center" class="form-label">Responsible Center</label>
                        <input id="correction-center" name="responsible_center" value="{{ old('responsible_center', $request->responsible_center) }}" class="form-control" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label for="correction-needed-date" class="form-label">Needed Date</label>
                        <input id="correction-needed-date" type="date" name="needed_date" value="{{ old('needed_date', $request->needed_date?->format('Y-m-d')) }}" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="correction-purpose" class="form-label">Purpose</label>
                        <textarea id="correction-purpose" name="purpose" class="form-control" rows="2" maxlength="1000" required>{{ old('purpose', $request->purpose) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label for="correction-remarks" class="form-label">Remarks</label>
                        <textarea id="correction-remarks" name="remarks" class="form-control" rows="2" maxlength="1000">{{ old('remarks', $request->remarks) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Requested items and quantities</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-correction-item">Add item</button>
                        </div>
                        <div id="correction-items">
                            @foreach($request->items as $index => $line)
                                <div class="row g-2 align-items-end mb-2 correction-item-row">
                                    <div class="col-md-8">
                                        <label class="form-label visually-hidden">Resource</label>
                                        <select name="items[{{ $index }}][inventory_item_id]" class="form-select" required>
                                            @if($line->item && ! $correctionItems->contains('id', $line->inventory_item_id))
                                                <option value="{{ $line->inventory_item_id }}" selected>{{ $line->item->name }} (inactive)</option>
                                            @endif
                                            @foreach($correctionItems as $inventoryItem)
                                                <option value="{{ $inventoryItem->id }}" @selected((int) old("items.$index.inventory_item_id", $line->inventory_item_id) === $inventoryItem->id)>
                                                    {{ $inventoryItem->name }} ({{ $inventoryItem->unit }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label visually-hidden">Quantity</label>
                                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity", $line->quantity) }}" min="1" max="2147483647" class="form-control" required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger remove-correction-item" aria-label="Remove item">×</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="correction-reason" class="form-label">Correction reason</label>
                        <textarea id="correction-reason" name="reason" class="form-control" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-warning">Submit correction proposal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <template id="correction-item-template">
        <div class="row g-2 align-items-end mb-2 correction-item-row">
            <div class="col-md-8">
                <select class="form-select" data-name="item" required>
                    <option value="">Select a resource</option>
                    @foreach($correctionItems as $inventoryItem)
                        <option value="{{ $inventoryItem->id }}">{{ $inventoryItem->name }} ({{ $inventoryItem->unit }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="number" class="form-control" data-name="quantity" min="1" max="2147483647" placeholder="Quantity" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-correction-item" aria-label="Remove item">×</button></div>
        </div>
    </template>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.getElementById('correction-items');
            const template = document.getElementById('correction-item-template');
            const addButton = document.getElementById('add-correction-item');

            const reindex = () => rows?.querySelectorAll('.correction-item-row').forEach((row, index) => {
                row.querySelector('[data-name="item"]')?.setAttribute('name', `items[${index}][inventory_item_id]`);
                row.querySelector('[data-name="quantity"]')?.setAttribute('name', `items[${index}][quantity]`);
                const controls = row.querySelectorAll('select, input');
                controls[0]?.setAttribute('name', `items[${index}][inventory_item_id]`);
                controls[1]?.setAttribute('name', `items[${index}][quantity]`);
            });

            addButton?.addEventListener('click', () => {
                if (!rows || !template) return;
                rows.append(template.content.cloneNode(true));
                reindex();
            });

            rows?.addEventListener('click', event => {
                const button = event.target.closest('.remove-correction-item');
                if (button && rows.querySelectorAll('.correction-item-row').length > 1) {
                    button.closest('.correction-item-row').remove();
                    reindex();
                }
            });
        });
    </script>
@endcan
