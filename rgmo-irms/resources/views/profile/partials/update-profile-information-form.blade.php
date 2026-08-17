<section>
    <header class="mb-4">
        <h3 class="h5 fw-bold mb-1">
            {{ __('Profile Information') }}
        </h3>

        <p class="text-muted small mb-0">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="row g-3">
        @csrf
        @method('patch')

        <div class="col-md-6">
            <label for="name" class="form-label fw-semibold small">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            @if($errors->get('name'))
                <div class="text-danger small mt-1">{{ $errors->first('name') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="email" class="form-label fw-semibold small">{{ __('Email Address') }}</label>
            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            @if($errors->get('email'))
                <div class="text-danger small mt-1">{{ $errors->first('email') }}</div>
            @endif

        </div>

        <div class="col-12 mt-4 d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-cmu px-4">{{ __('Save Changes') }}</button>

            @if (session('status') === 'profile-updated')
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
