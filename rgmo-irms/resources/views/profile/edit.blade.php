<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Account Profile</h2>
        <p class="text-muted mb-0 small">Manage your account settings, security, and preferences.</p>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-10">
                <x-breadcrumb :items="['Profile' => route('profile.edit')]" />
                
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

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        @include('profile.partials.session-controls')
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="stat-icon flex-shrink-0" aria-hidden="true">
                                    <i data-lucide="panel-left"></i>
                                </div>
                                <div>
                                    <h3 class="h5 fw-bold mb-1">Navigation Preferences</h3>
                                    <p class="text-muted small mb-0">Restore the original module order in your sidebar.</p>
                                    @if(session('status') === 'sidebar-order-reset')
                                        <p class="small text-success fw-semibold mt-2 mb-0" role="status">Default sidebar order restored.</p>
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('profile.sidebar-order.reset') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" @disabled(empty($user->sidebar_order))>
                                    <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                                    Reset Order
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
                    <div class="card-body p-4">
                        @include('profile.partials.two-factor')
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
