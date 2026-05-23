<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="auth-label">{{ __('Email Address') }}</label>
            <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@example.com" />
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="password" class="auth-label">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a class="forgot-link" href="{{ route('password.request') }}">
                        {{ __('Forgot?') }}
                    </a>
                @endif
            </div>
            <div class="input-group">
                <input id="password" class="form-control @error('password') is-invalid @enderror"
                                type="password"
                                name="password"
                                required placeholder="••••••••" />
                <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-color: #dee2e6; color: #6b7280">
                    <i data-lucide="eye" id="toggleIcon" style="width: 18px; height: 18px"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="mb-4 d-flex align-items-center">
            <div class="form-check">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember" style="accent-color: var(--cmu-green)">
                <label for="remember_me" class="form-check-label small text-muted ms-1">{{ __('Remember me') }}</label>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-cmu d-flex align-items-center justify-content-center gap-2">
                <i data-lucide="log-in" style="width: 18px; height: 18px"></i>
                {{ __('Login') }}
            </button>
        </div>
    </form>

    <script>
        lucide.createIcons();

        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                password.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        });
    </script>
</x-guest-layout>
