<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Account Profile</h2>
        <p class="text-muted mb-0 small">Manage your account settings, security, and preferences.</p>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
                    <div class="card-body p-4">
                        @include('profile.partials.two-factor')
                    </div>
                </div>

                <div class="card border-0 shadow-sm border-start border-4 border-danger">
                    <div class="card-body p-4">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
