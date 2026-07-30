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
                    linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                    linear-gradient(135deg, #003414 0%, #005323 54%, #113f23 100%) !important;
                background-size: 44px 44px, 44px 44px, auto;
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
                background:
                    linear-gradient(90deg, rgba(255, 198, 0, 0.12), transparent 32%),
                    linear-gradient(180deg, transparent 0%, rgba(0, 22, 9, 0.26) 100%);
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
                background: rgba(253, 255, 250, 0.98) !important;
                transform: translateY(-2px);
                border-color: var(--cmu-yellow) !important;
                box-shadow: 0 18px 36px rgba(0, 30, 12, 0.2);
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
                background: #fbfdf9;
                backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 198, 0, 0.58);
                border-radius: 10px;
                box-shadow: 0 30px 70px rgba(0, 24, 10, 0.34);
                width: 100%;
                max-width: 500px;
                padding: 2.75rem;
                position: relative;
                overflow: hidden;
            }

            .login-card::before {
                content: "";
                position: absolute;
                inset: 0 0 auto 0;
                height: 5px;
                background: linear-gradient(90deg, var(--cmu-yellow), var(--cmu-green-2), var(--cmu-accent));
            }

            .login-card .brand-mark {
                width: 64px;
                height: 64px;
                background: var(--cmu-green);
                border: 1px solid rgba(255, 255, 255, 0.14);
            }

            .auth-mobile-brand {
                display: flex;
                align-items: center;
                gap: 0.8rem;
                margin-bottom: 1.25rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid rgba(0, 73, 30, 0.1);
            }

            .auth-mobile-brand img {
                width: 52px;
                height: 52px;
                flex: 0 0 52px;
                object-fit: contain;
            }

            .auth-mobile-brand strong,
            .auth-mobile-brand span {
                display: block;
            }

            .auth-mobile-brand strong {
                color: var(--cmu-green);
                font-size: 1.05rem;
                font-weight: 800;
                line-height: 1.15;
                letter-spacing: -0.01em;
            }

            .auth-mobile-brand span {
                margin-top: 0.2rem;
                color: #627169;
                font-size: 0.68rem;
                font-weight: 700;
                line-height: 1.25;
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

            .auth-brand-lockup {
                display: flex;
                align-items: center;
                gap: 1.25rem;
                margin-bottom: 2.25rem;
            }

            .auth-brand-lockup > div {
                min-width: 0;
            }

            .auth-brand-lockup img {
                width: 104px;
                height: 104px;
                object-fit: contain;
            }

            .auth-brand-lockup .office-name {
                color: rgba(244, 255, 246, 0.86);
                font-size: 0.76rem;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                margin-bottom: 0.35rem;
            }

            .auth-title {
                color: #ffffff;
                font-size: clamp(3rem, 4.4vw, 4.2rem);
                line-height: 0.92;
                font-weight: 800;
                letter-spacing: 0;
                margin: 0;
                white-space: nowrap;
            }

            .auth-subtitle {
                max-width: 560px;
                color: rgba(244, 255, 246, 0.9);
                font-size: 1.08rem;
                line-height: 1.7;
                margin: 0 0 2rem;
                border-left: 3px solid var(--cmu-yellow);
                padding-left: 1.25rem;
            }

            .auth-panel-title {
                color: rgba(244, 255, 246, 0.78);
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                margin: 1.75rem 0 0.85rem;
            }

            .auth-card-heading {
                margin-bottom: 1.4rem;
            }

            .auth-card-heading h2 {
                color: #1f2d27;
                font-size: 2rem;
                font-weight: 800;
                line-height: 1.1;
                margin: 0 0 0.45rem;
            }

            .auth-card-heading p {
                color: #65736b;
                line-height: 1.55;
                margin: 0;
            }

            .auth-notice {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                color: #244335;
                background: rgba(2, 104, 30, 0.08);
                border: 1px solid rgba(2, 104, 30, 0.18);
                border-radius: 8px;
                padding: 0.75rem 0.9rem;
                font-size: 0.84rem;
                font-weight: 700;
            }

            .auth-notice svg {
                width: 18px;
                height: 18px;
                color: var(--cmu-green-2);
                flex: 0 0 auto;
            }

            .auth-form {
                display: grid;
                gap: 1rem;
            }

            .auth-field {
                display: grid;
                gap: 0.45rem;
            }

            .auth-input-wrap,
            .auth-password-group {
                position: relative;
            }

            .auth-input-wrap svg,
            .auth-password-icon svg {
                width: 18px;
                height: 18px;
                color: #6d7a72;
            }

            .auth-input-wrap > svg,
            .auth-password-icon {
                position: absolute;
                left: 0.9rem;
                top: 50%;
                transform: translateY(-50%);
                z-index: 4;
            }

            .auth-input-wrap .form-control,
            .auth-password-group .form-control {
                padding-left: 2.65rem;
                min-height: 48px;
            }

            .auth-password-group .form-control {
                border-top-right-radius: 0 !important;
                border-bottom-right-radius: 0 !important;
            }

            .auth-icon-button {
                width: 48px;
                min-height: 48px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(0, 73, 30, 0.28);
                border-left: 0;
                color: #53645b;
                background: #ffffff;
                border-top-right-radius: 8px !important;
                border-bottom-right-radius: 8px !important;
            }

            .auth-icon-button:hover,
            .auth-icon-button:focus {
                color: var(--cmu-green);
                background: #f4faf3;
                border-color: rgba(0, 73, 30, 0.34);
            }

            .auth-icon-button svg,
            .btn-cmu svg {
                width: 18px;
                height: 18px;
            }

            .auth-options {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 0.15rem;
            }

            .auth-options .form-check {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0;
                min-height: 24px;
            }

            .auth-options .form-check-input {
                margin: 0;
                box-shadow: none;
            }

            .auth-options .form-check-label {
                color: #52645a !important;
                font-size: 0.86rem;
            }

            .btn-cmu {
                min-height: 48px;
                border-radius: 8px;
            }

            .auth-card-footer {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                margin-top: 1.4rem;
                padding-top: 1.2rem;
                border-top: 1px solid rgba(0, 73, 30, 0.1);
                color: #69776f;
                font-size: 0.84rem;
                text-align: center;
                flex-wrap: wrap;
            }

            .auth-card-footer strong {
                color: var(--cmu-green);
            }

            @media (max-width: 991.98px) {
                body {
                    align-items: flex-start;
                }

                .login-card {
                    padding: 2rem;
                }
            }

            @media (max-width: 575.98px) {
                .container {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }

                .login-card {
                    padding: 1.5rem;
                }

                .auth-card-heading h2 {
                    font-size: 1.7rem;
                }
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
                        <div class="auth-brand-lockup">
                            <img src="{{ asset('images/logo.png') }}" alt="CMU Logo" class="cmu-logo" onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                            <div>
                                <div class="office-name">Resource Generation Management Office</div>
                                <h1 class="auth-title">RGMO-IRMS</h1>
                            </div>
                        </div>
                        <p class="auth-subtitle">Inventory and resource operations for agricultural assets, supplies issuance, project distribution, monitoring, and reporting.</p>

                        <div class="auth-panel-title">Core Workflows</div>
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
                        <div class="auth-mobile-brand d-lg-none">
                            <img src="{{ asset('images/logo.png') }}" alt="RGMO-IRMS logo" class="cmu-logo">
                            <div>
                                <strong>RGMO-IRMS</strong>
                                <span>Central Mindanao University</span>
                            </div>
                        </div>
                        
                        {{ $slot }}
                    </div>
                    
                </div>
            </div>
        </div>
        
        <script>
            lucide.createIcons();
        </script>
    </body>
</html>
