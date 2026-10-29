<details class="header-account" id="headerAccount">
    <summary aria-label="Account menu for {{ Auth::user()->name }}" title="Account menu">
        <span class="header-account-avatar" aria-hidden="true">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</span>
        <i data-lucide="chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="header-account-panel">
        <div class="header-account-info">
            <strong>{{ Auth::user()->name }}</strong>
            <span>{{ Auth::user()->email }}</span>
            <small>{{ str(Auth::user()->role)->replace('_', ' ')->title() }}</small>
        </div>
        <a href="{{ route('profile.edit') }}" class="header-account-action">
            <i data-lucide="user-round" aria-hidden="true"></i>
            Profile
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="header-account-action">
                <i data-lucide="log-out" aria-hidden="true"></i>
                Sign out
            </button>
        </form>
    </div>
</details>
<script>
    (() => {
        const account = document.getElementById('headerAccount');
        document.addEventListener('click', (event) => {
            if (!account.contains(event.target)) account.open = false;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && account.open) {
                account.open = false;
                account.querySelector('summary').focus();
            }
        });
    })();
</script>
