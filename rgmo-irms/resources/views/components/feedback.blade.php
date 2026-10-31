<div class="rgmo-toasts" aria-label="Notifications">
    @foreach (['success', 'warning', 'error', 'info'] as $type)
        @if (session($type))
            <div data-flash-toast="{{ $type }}" hidden>{{ session($type) }}</div>
        @endif
    @endforeach
</div>

<dialog class="rgmo-confirm" aria-labelledby="rgmo-confirm-title" aria-describedby="rgmo-confirm-message">
    <div class="rgmo-confirm__symbol" aria-hidden="true">!</div>
    <h2 id="rgmo-confirm-title">Confirm action</h2>
    <p id="rgmo-confirm-message"></p>
    <div class="rgmo-confirm__actions">
        <button type="button" class="btn btn-outline-secondary" data-confirm-cancel autofocus>Cancel</button>
        <button type="button" class="btn btn-cmu" data-confirm-accept>Confirm</button>
    </div>
</dialog>
