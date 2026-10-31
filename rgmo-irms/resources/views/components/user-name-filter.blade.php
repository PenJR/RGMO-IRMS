@props(['users', 'selected' => ''])
<div class="user-name-filter" data-user-name-filter>
    <label class="form-label" for="usage-user-select" data-user-label>Recorded by</label>
    <select id="usage-user-select" name="user_id" class="form-select" data-user-select>
        <option value="">All users</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}" @selected((string) $selected === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
        @endforeach
    </select>
    <div data-user-search-wrapper hidden>
        <input id="usage-user-search" type="text" class="form-control" placeholder="Type a name…" autocomplete="off"
            role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="usage-user-options"
            aria-describedby="usage-user-help" data-user-search>
        <ul id="usage-user-options" class="user-name-filter__options" role="listbox" aria-label="Recorded by" hidden data-user-options></ul>
    </div>
    <div class="form-text" id="usage-user-help">Leave blank to show all users.</div>
</div>
