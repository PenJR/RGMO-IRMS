<x-guest-layout>
    <div class="auth-card-heading">
        <h2>Verify your identity</h2>
        <p>Enter the code from your authenticator app, or use one of your recovery codes.</p>
    </div>

    <div class="auth-notice mb-4">
        <i data-lucide="shield-check" aria-hidden="true"></i>
        <span>This additional step protects your RGMO-IRMS account.</span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert" id="twoFactorError" tabindex="-1">
            <i data-lucide="circle-alert" aria-hidden="true"></i>
            <div>
                <strong>Verification unsuccessful</strong>
                <div>{{ $errors->first() }}</div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('2fa.verify.post') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="code" class="auth-label">Authentication or recovery code</label>
            <div class="auth-input-wrap">
                <i data-lucide="key-round" aria-hidden="true"></i>
                <input
                    id="code"
                    class="form-control @error('code') is-invalid @enderror"
                    name="code"
                    type="text"
                    value="{{ old('code') }}"
                    required
                    autofocus
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="Enter your 6-digit code"
                    aria-describedby="twoFactorHelp{{ $errors->any() ? ' twoFactorError' : '' }}"
                    aria-invalid="{{ $errors->any() ? 'true' : 'false' }}"
                >
            </div>
            <p class="form-text mb-0" id="twoFactorHelp">Recovery codes may contain letters, numbers, and a hyphen.</p>
        </div>

        <div class="d-grid mt-3">
            <button type="submit" class="btn btn-cmu d-flex align-items-center justify-content-center gap-2">
                <i data-lucide="shield-check" aria-hidden="true"></i>
                Verify and continue
            </button>
        </div>
    </form>

    <div class="auth-card-footer">
        <span>Having trouble?</span>
        <strong>Contact the system administrator.</strong>
    </div>

    @if($errors->any())
        <script>
            window.addEventListener('load', () => document.getElementById('twoFactorError')?.focus(), { once: true });
        </script>
    @endif
</x-guest-layout>
