<section>
    <header class="mb-4">
        <h3 class="h5 fw-bold mb-1">
            {{ __('Delete Account') }}
        </h3>

        <p class="text-muted small mb-0">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <button type="button" class="btn btn-danger px-4" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
        {{ __('Delete Account') }}
    </button>

    <!-- Modal -->
    <div class="modal fade" id="confirmUserDeletion" tabindex="-1" aria-labelledby="confirmUserDeletionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="{{ route('profile.destroy') }}" class="modal-content">
                @csrf
                @method('delete')

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" id="confirmUserDeletionLabel">{{ __('Confirm Deletion') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p class="text-dark">
                        {{ __('Are you sure you want to delete your account?') }}
                    </p>
                    <p class="text-muted small">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <div class="mt-4">
                        <label for="password" class="form-label fw-semibold small">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" class="form-control" placeholder="{{ __('Password') }}" />
                        @if($errors->userDeletion->get('password'))
                            <div class="text-danger small mt-1">{{ $errors->userDeletion->first('password') }}</div>
                        @endif
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger ms-3">{{ __('Delete Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
