<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --cmu-yellow: #FFC600;
                --cmu-green: #00491E;
                --cmu-green-2: #02681E;
                --cmu-accent: #919F02;
                --cmu-yellow-hover: #E5B700;
            }

            body {
                background:
                    linear-gradient(135deg, #003c19 0%, #005323 48%, #17451f 100%) !important;
                font-family: 'Inter', system-ui, sans-serif;
                color: #f4fff6;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow-x: hidden;
            }

            body::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
                z-index: 0;
            }

            .floating-blobs {
                display: none;
            }

            .blob {
                display: none;
            }

            @keyframes float {
                from { transform: translate(0, 0) scale(1); }
                to { transform: translate(100px, 100px) scale(1.2); }
            }

            .transition-all {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .feature-card:hover {
                background: rgba(244, 255, 246, 0.98) !important;
                transform: translateY(-2px);
                border-color: var(--cmu-yellow) !important;
                box-shadow: 0 14px 30px rgba(0, 30, 12, 0.18);
            }

            .feature-card:hover h5 {
                color: var(--cmu-green) !important;
            }

            .feature-card:hover p {
                color: #4f6458 !important;
            }

            .feature-card {
                border: 1px solid rgba(255, 198, 0, 0.36);
            }

            .login-card {
                background: rgba(250, 253, 249, 0.98);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 198, 0, 0.45);
                border-radius: 12px;
                box-shadow: 0 26px 60px rgba(0, 28, 12, 0.28);
                width: 100%;
                max-width: 480px;
                padding: 3rem;
            }

            .login-card .brand-mark {
                width: 64px;
                height: 64px;
                background: var(--cmu-green);
                border: 1px solid rgba(255, 255, 255, 0.14);
            }

            .input-group-text {
                background: transparent;
                border-right: none;
            }

            .form-control {
                border-radius: 8px;
                padding: 0.75rem 1rem;
                border-color: rgba(0, 73, 30, 0.28);
            }

            .form-control:focus {
                box-shadow: 0 0 0 4px rgba(255, 198, 0, 0.22);
                border-color: var(--cmu-yellow);
            }

            .form-check-input:checked {
                background-color: var(--cmu-green);
                border-color: var(--cmu-green);
            }

            .auth-shell-copy {
                color: #f4fff6;
            }

            .auth-shell-copy p,
            .auth-shell-copy .small {
                color: rgba(244, 255, 246, 0.95);
            }

            .feature-card h5 {
                color: var(--cmu-green) !important;
            }

            .feature-card p {
                color: #4f6458 !important;
                opacity: 1 !important;
            }

            .cmu-logo {
                filter:
                    drop-shadow(0 0 1px rgba(255, 255, 255, 0.85))
                    drop-shadow(0 0 3px rgba(255, 255, 255, 0.55))
                    drop-shadow(0 0 12px rgba(255, 198, 0, 0.18));
                transition: transform 0.3s ease;
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="rgmo-loader" data-rgmo-loader role="status" aria-live="polite" aria-busy="true">
            <div class="rgmo-loader__mark" aria-hidden="true">
                <img src="{{ asset('images/logo.png') }}" alt="" class="rgmo-loader__logo">
                <span class="rgmo-loader__track"></span>
            </div>
            <span class="visually-hidden">Loading</span>
        </div>

        <div class="floating-blobs">
            <div class="blob" style="top: -10%; left: -10%; background: var(--cmu-yellow); animation-delay: 0s; opacity: 0.16;"></div>
            <div class="blob" style="bottom: -10%; right: -10%; background: var(--cmu-accent); animation-delay: -5s; opacity: 0.16;"></div>
        </div>

        <div class="container py-5">
            <div class="row align-items-center justify-content-center g-5 min-vh-100">
                <!-- Info Column (Visible on Desktop) -->
                <div class="col-lg-6 d-none d-lg-block auth-shell-copy">
                    <div class="pe-lg-5">
                        <img src="{{ asset('images/logo.png') }}" alt="CMU Logo" class="cmu-logo" style="width: 120px; height: 120px; margin-bottom: 2.5rem;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <h1 class="display-3 fw-bold mb-2" style="color: #ffffff; letter-spacing: -0.04em;">RGMO-IRMS</h1>
                        <p class="lead fw-medium mb-5" style="font-size: 1.25rem; border-left: 3px solid var(--cmu-yellow); padding-left: 1.5rem;">Resource Generation Management Office<br><span class="opacity-75" style="font-size: 1.1rem;">Inventory & Resource Management System</span></p>
                        
                        <div class="auth-panel">
                            <div class="feature-card auth-feature-card d-flex align-items-center gap-4 p-3 transition-all">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm brand-mark" style="min-width: 60px;">
                                    <i data-lucide="package" class="text-success" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-green)">Asset Tracking</h5>
                                    <p class="small mb-0 opacity-75">Precision monitoring of agricultural stocks and nursery equipment.</p>
                                </div>
                            </div>
                            <div class="feature-card auth-feature-card d-flex align-items-center gap-4 p-3 transition-all">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm brand-mark" style="min-width: 60px;">
                                    <i data-lucide="leaf" class="text-success" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-green)">Sustainability Focus</h5>
                                    <p class="small mb-0 opacity-75">Promoting green initiatives through digital resource optimization.</p>
                                </div>
                            </div>
                            <div class="feature-card auth-feature-card d-flex align-items-center gap-4 p-3 transition-all">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm brand-mark" style="min-width: 60px;">
                                    <i data-lucide="file-check" class="text-success" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-green)">Resource Allocation</h5>
                                    <p class="small mb-0 opacity-75">Optimized workflow for supplies issuance and project distribution.</p>
                                </div>
                            </div>
                            <div class="feature-card auth-feature-card d-flex align-items-center gap-4 p-3 transition-all">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm brand-mark" style="min-width: 60px;">
                                    <i data-lucide="sparkles" class="text-success" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-green)">AI Insights</h5>
                                    <p class="small mb-0 opacity-75">Data-driven forecasting to minimize wastage and predict demand.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login Card Column -->
                <div class="col-lg-5 col-md-8">
                    <div class="login-card mx-auto">
                        <div class="brand-logo d-lg-none">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="cmu-logo" style="width: 54px; height: 54px;">
                        </div>
                        
                        {{ $slot }}
                    </div>
                    
                    <div class="text-center mt-4 opacity-50 d-lg-none" style="color: #4f6458;">
                        <p class="small">© {{ date('Y') }} Central Mindanao University</p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            lucide.createIcons();
        </script>
    </body>
</html>
