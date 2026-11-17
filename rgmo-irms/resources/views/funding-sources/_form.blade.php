@csrf
<div class="row g-3">
    <div class="col-md-4">
        <label for="funding-source-code" class="form-label">Code <span class="text-danger">*</span></label>
        <input id="funding-source-code" @unless($fundingSource->exists) name="code" @endunless type="text" class="form-control @error('code') is-invalid @enderror" required maxlength="{{ $fundingSource->exists ? 255 : 80 }}" value="{{ $fundingSource->exists ? $fundingSource->code : old('code') }}" @readonly($fundingSource->exists) aria-describedby="funding-source-code-help">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div id="funding-source-code-help" class="form-text">{{ $fundingSource->exists ? 'The code identifies historical records and cannot be changed.' : 'Use a unique code. New codes are saved in uppercase.' }}</div>
    </div>
    <div class="col-md-8">
        <label for="funding-source-name" class="form-label">Name <span class="text-danger">*</span></label>
        <input id="funding-source-name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" required maxlength="255" value="{{ old('name', $fundingSource->name) }}">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="funding-source-description" class="form-label">Description</label>
        <textarea id="funding-source-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3" maxlength="5000">{{ old('description', $fundingSource->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="funding-source-status" class="form-label">Status</label>
        <select id="funding-source-status" name="status" class="form-select @error('status') is-invalid @enderror" required aria-describedby="funding-source-status-help">@foreach(App\Models\FundingSource::statuses() as $status)<option value="{{ $status }}" @selected(old('status', $fundingSource->status) === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div id="funding-source-status-help" class="form-text">Inactive sources remain on existing records and cannot be selected for new purchases.</div>
    </div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-cmu" type="submit">Save</button><a href="{{ $fundingSource->exists ? route('funding-sources.show', $fundingSource) : route('funding-sources.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
