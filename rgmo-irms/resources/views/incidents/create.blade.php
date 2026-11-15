<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Report an incident</h2></x-slot>
    <div class="container py-4">
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body p-4"><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="category">Category</label><select class="form-select" name="category" id="category" required><option value="">Select category</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="severity">Severity</label><select class="form-select" name="severity" id="severity" required>@foreach(['low','medium','high','critical'] as $severity)<option value="{{ $severity }}" @selected(old('severity', 'medium') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="summary">Summary</label><input class="form-control" name="summary" id="summary" maxlength="255" value="{{ old('summary') }}" required></div>
                <div class="col-12"><label class="form-label" for="description">What happened?</label><textarea class="form-control" name="description" id="description" rows="5" maxlength="10000" required>{{ old('description') }}</textarea></div>
                <div class="col-md-6"><label class="form-label" for="occurred_at">Date and time observed</label><input class="form-control" type="datetime-local" name="occurred_at" id="occurred_at" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('occurred_at') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="evidence">Evidence (up to 5 files)</label><input class="form-control" type="file" name="evidence[]" id="evidence" accept=".jpg,.jpeg,.png,.pdf" multiple><div class="form-text">JPG, PNG, or PDF; 10 MB maximum per file. Evidence is private.</div></div>
                <div class="col-md-6"><label class="form-label" for="related_type">Related record (optional)</label><select class="form-select" name="related_type" id="related_type"><option value="">No linked record</option>@foreach($relatedTypes as $type => $config)<option value="{{ $type }}" @selected(old('related_type') === $type)>{{ $config['label'] }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="related_id">Related record ID (optional)</label><input class="form-control" type="number" min="1" name="related_id" id="related_id" value="{{ old('related_id') }}"><div class="form-text">Choose the record type above and enter its ID.</div></div>
            </div></div>
            <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-cmu">Submit report</button></div>
        </form>
    </div>
</x-app-layout>
