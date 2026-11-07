<section aria-labelledby="session-controls-heading">
    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
        <div>
            <h3 class="h5 fw-bold mb-1" id="session-controls-heading">Cookies &amp; Sessions</h3>
        </div>
        @if($sessionControls['enabled'] && $sessionControls['sessions']->where('current', false)->isNotEmpty())
            <form method="POST" action="{{ route('profile.sessions.destroy-others') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-2">
                    <i data-lucide="log-out" aria-hidden="true" style="width: 15px; height: 15px;"></i>
                    Sign out other sessions
                </button>
            </form>
        @endif
    </div>

    @if(session('status') === 'session-revoked' || session('status') === 'other-sessions-revoked')
        <div class="alert alert-success py-2 small" role="status">
            {{ session('status') === 'session-revoked' ? 'The selected session was signed out.' : 'All other sessions were signed out.' }}
        </div>
    @endif

    <details class="mb-4">
        <summary class="small fw-semibold">Help</summary>
    <div class="row g-3 mt-1">
        <div class="col-12 col-md-4">
            <div class="rounded-3 border bg-light p-3 h-100">
                <p class="small fw-bold mb-1">Essential cookies only</p>
                <p class="text-muted small mb-0">For sign-in, security, and saved preferences.</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="rounded-3 border bg-light p-3 h-100">
                <p class="small fw-bold mb-1">Cookie protection</p>
                <p class="text-muted small mb-0">HttpOnly {{ $sessionControls['http_only'] ? 'enabled' : 'disabled' }} · SameSite {{ $sessionControls['same_site'] }}{{ $sessionControls['secure'] ? ' · HTTPS only' : '' }}</p>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="rounded-3 border bg-light p-3 h-100">
                <p class="small fw-bold mb-1">Automatic expiry</p>
                <p class="text-muted small mb-0">After {{ $sessionControls['lifetime'] }} minutes inactive{{ $sessionControls['expire_on_close'] ? ' or when the browser closes' : '' }}.</p>
            </div>
        </div>
    </div>
    </details>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
        <h4 class="h6 fw-bold mb-0">Signed-in browsers</h4>
        <span class="badge bg-light text-dark border">{{ $sessionControls['sessions']->count() }}</span>
    </div>

    <div class="list-group list-group-flush border rounded-3 overflow-hidden">
        @foreach($sessionControls['sessions'] as $browserSession)
            <div class="list-group-item p-3">
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div class="stat-icon flex-shrink-0" aria-hidden="true">
                            <i data-lucide="{{ str_contains($browserSession['device'], 'Android') || str_contains($browserSession['device'], 'iOS') ? 'smartphone' : 'monitor' }}"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="fw-bold small mb-1 text-truncate">
                                {{ $browserSession['device'] }}
                                @if($browserSession['current'])
                                    <span class="badge text-bg-success ms-1">Current</span>
                                @endif
                            </p>
                            <p class="text-muted small mb-0 text-truncate">{{ $browserSession['ip_address'] }} · Active {{ $browserSession['last_active']->diffForHumans() }}</p>
                        </div>
                    </div>
                    @if(! $browserSession['current'] && $sessionControls['enabled'])
                        <form method="POST" action="{{ route('profile.sessions.destroy', $browserSession['id']) }}" class="align-self-end align-self-sm-center">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Sign out</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if(! $sessionControls['enabled'])
        <p class="text-muted small mt-3 mb-0">Individual sign-out is unavailable with the current session settings.</p>
    @endif
</section>
