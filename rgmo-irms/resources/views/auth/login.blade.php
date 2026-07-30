<x-guest-layout>
    <div class="auth-card-heading">
        <h2>Welcome back</h2>
        <p>Sign in to manage inventory, resource requests, reports, and approvals.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="auth-notice mb-4">
        <i data-lucide="shield-check" aria-hidden="true"></i>
        <span>Authorized RGMO personnel only</span>
    </div>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <!-- Email Address -->
        <div class="auth-field">
            <label for="email" class="auth-label">{{ __('Email Address') }}</label>
            <div class="auth-input-wrap">
                <i data-lucide="mail" aria-hidden="true"></i>
                <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@example.com" />
            </div>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="auth-field">
            <div class="d-flex justify-content-between">
                <label for="password" class="auth-label">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a class="forgot-link" href="{{ route('password.request') }}">
                        {{ __('Forgot?') }}
                    </a>
                @endif
            </div>
            <div class="input-group auth-password-group">
                <span class="auth-password-icon"><i data-lucide="lock-keyhole" aria-hidden="true"></i></span>
                <input id="password" class="form-control @error('password') is-invalid @enderror"
                                type="password"
                                name="password"
                                required placeholder="••••••••" />
                <button class="btn auth-icon-button" type="button" id="togglePassword" aria-label="Show password">
                    <i data-lucide="eye" id="toggleIcon"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="auth-options">
            <div class="form-check">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label for="remember_me" class="form-check-label">{{ __('Remember me') }}</label>
            </div>
        </div>

        <div class="d-grid mt-4">
            <button type="submit" class="btn btn-cmu d-flex align-items-center justify-content-center gap-2">
                <i data-lucide="log-in" aria-hidden="true"></i>
                {{ __('Login') }}
            </button>
        </div>
    </form>

    <div class="auth-card-footer">
        <span>Need access?</span>
        <strong>Contact the system administrator.</strong>
    </div>

    <script>
        lucide.createIcons();

        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                password.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
                this.setAttribute('aria-label', 'Show password');
            }
            lucide.createIcons();
        });
    </script>
</x-guest-layout>
