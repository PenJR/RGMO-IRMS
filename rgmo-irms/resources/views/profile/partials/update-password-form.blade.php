<section>
    <header class="mb-4">
        <h3 class="h5 fw-bold mb-1">
            {{ __('Update Password') }}
        </h3>

        <p class="text-muted small mb-0">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="row g-3">
        @csrf
        @method('put')

        <div class="col-md-6">
            <label for="update_password_current_password" class="form-label fw-semibold small">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="form-control" autocomplete="current-password" />
            @if($errors->updatePassword->get('current_password'))
                <div class="text-danger small mt-1">{{ $errors->updatePassword->first('current_password') }}</div>
            @endif
        </div>

        <div class="col-12 row g-3">
            <div class="col-md-6">
                <label for="update_password_password" class="form-label fw-semibold small">{{ __('New Password') }}</label>
                <input id="update_password_password" name="password" type="password" class="form-control" autocomplete="new-password" />
                @if($errors->updatePassword->get('password'))
                    <div class="text-danger small mt-1">{{ $errors->updatePassword->first('password') }}</div>
                @endif
            </div>

            <div class="col-md-6">
                <label for="update_password_password_confirmation" class="form-label fw-semibold small">{{ __('Confirm Password') }}</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" />
                @if($errors->updatePassword->get('password_confirmation'))
                    <div class="text-danger small mt-1">{{ $errors->updatePassword->first('password_confirmation') }}</div>
                @endif
            </div>
        </div>

        <div class="col-12 mt-4 d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-cmu px-4">{{ __('Update Password') }}</button>

            @if (session('status') === 'password-updated')
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-success small"
                >
                    <i data-lucide="check" class="me-1" style="width: 14px"></i>
                    {{ __('Saved.') }}
                </div>
            @endif
        </div>
    </form>
</section>
