<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Create New User</h2>
            <p class="text-muted mb-0">Add a staff account to the system.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('admin.users.store') }}" method="POST" id="create-user-form">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-password" class="form-label">Password</label>
                            <input id="new-user-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" aria-describedby="new-user-password-error" required maxlength="255">
                            <div id="new-user-password-error" class="invalid-feedback">@error('password'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-password-confirmation" class="form-label">Confirm Password</label>
                            <input id="new-user-password-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" aria-describedby="password-match-feedback" required maxlength="255">
                            <div id="password-match-feedback" class="invalid-feedback" aria-live="polite"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Choose role</option>
                                @foreach(App\Models\User::availableRoles() as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>{{ Str::of($role)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 d-flex gap-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-cmu">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('create-user-form');
                const password = document.getElementById('new-user-password');
                const confirmation = document.getElementById('new-user-password-confirmation');
                const feedback = document.getElementById('password-match-feedback');

                const checkPasswordMatch = () => {
                    const mismatch = confirmation.value.length > 0 && password.value !== confirmation.value;
                    const message = mismatch ? 'Passwords do not match. Please enter the same password in both fields.' : '';
                    confirmation.setCustomValidity(message);
                    confirmation.classList.toggle('is-invalid', mismatch);
                    confirmation.setAttribute('aria-invalid', String(mismatch));
                    feedback.textContent = message;
                    return !mismatch;
                };

                password.addEventListener('input', checkPasswordMatch);
                confirmation.addEventListener('input', checkPasswordMatch);
                form.addEventListener('submit', (event) => {
                    if (!checkPasswordMatch()) {
                        event.preventDefault();
                        confirmation.reportValidity();
                        confirmation.focus();
                    }
                });
                checkPasswordMatch();
            })();
        </script>
    @endpush
</x-app-layout>
