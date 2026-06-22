<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }} - {{ isset($header) ? strip_tags($header) : 'Dashboard' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --cmu-yellow: #FFC600;
                --cmu-yellow-hover: #E6B300;
                --cmu-green: #00491E;
                --cmu-green-2: #02681E;
                --cmu-accent: #919F02;
                --sidebar-width: 260px;
            }

            body {
                background:
                    radial-gradient(circle at top left, rgba(255, 198, 0, 0.08), transparent 30%),
                    linear-gradient(180deg, #ffffff 0%, #f6f8f6 100%);
                font-family: 'Inter', system-ui, sans-serif;
                color: #163024;
            }

            #sidebar {
                width: var(--sidebar-width);
                min-width: var(--sidebar-width);
                flex-shrink: 0;
                min-height: 100vh;
                background: linear-gradient(180deg, var(--cmu-green) 0%, #003519 100%);
                color: white;
                display: flex;
                flex-direction: column;
                box-shadow: 8px 0 30px rgba(0, 73, 30, 0.14);
            }

            #sidebar .nav-link {
                color: rgba(255, 255, 255, 0.78);
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
                transition: all 0.2s;
                border-left: 4px solid transparent;
                display: flex;
                align-items: center;
            }

            #sidebar .nav-link [data-lucide],
            #sidebar .nav-group-toggle [data-lucide] {
                width: 18px;
                height: 18px;
                stroke-width: 2.25;
                color: rgba(255, 255, 255, 0.92);
                flex-shrink: 0;
            }

            #sidebar .nav-link.active [data-lucide],
            #sidebar .nav-group.active > summary .nav-group-toggle [data-lucide],
            #sidebar .nav-submenu .nav-link.active [data-lucide] {
                color: var(--cmu-green);
            }

            #sidebar .nav-link:hover [data-lucide],
            #sidebar .nav-group-toggle:hover [data-lucide] {
                color: #ffffff;
            }

            #sidebar .nav-link:hover {
                color: white;
                background: rgba(255, 255, 255, 0.08);
                padding-left: 1.75rem;
                text-decoration: none;
            }

            #sidebar .nav-link.active {
                color: var(--cmu-green);
                background: var(--cmu-yellow);
                border-left-color: var(--cmu-yellow);
                font-weight: 600;
                padding-left: 1.75rem;
                box-shadow: inset 0 0 0 1px rgba(0, 73, 30, 0.04);
            }

            #sidebar .nav-group {
                margin: 0.125rem 0;
                transition: all 0.3s ease;
            }

            #sidebar .nav-group summary {
                list-style: none;
                cursor: pointer;
                user-select: none;
            }

            #sidebar .nav-group summary::-webkit-details-marker {
                display: none;
            }

            #sidebar .nav-group-toggle {
                color: rgba(255, 255, 255, 0.78);
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
                transition: all 0.2s;
                border-left: 4px solid transparent;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            #sidebar .nav-group-toggle:hover,
            #sidebar .nav-group[open] > summary .nav-group-toggle {
                color: white;
                background: rgba(255, 255, 255, 0.1);
            }

            #sidebar .nav-group.active > summary .nav-group-toggle {
                color: var(--cmu-yellow);
                border-left-color: var(--cmu-yellow);
                font-weight: 600;
            }

            #sidebar .nav-group-toggle .chevron {
                margin-left: auto;
                width: 14px;
                height: 14px;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                opacity: 0.5;
            }

            #sidebar .nav-group[open] .chevron {
                transform: rotate(180deg);
                opacity: 1;
            }

            #sidebar .nav-submenu {
                padding: 0.25rem 0 0.5rem;
                background: rgba(255, 255, 255, 0.04);
                overflow: hidden;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #sidebar .nav-submenu .nav-link {
                padding: 0.55rem 1.5rem 0.55rem 3.25rem;
                border-left-width: 4px;
                font-size: 0.8125rem;
                opacity: 0.8;
            }

            #sidebar .nav-submenu .nav-link:hover,
            #sidebar .nav-submenu .nav-link.active {
                opacity: 1;
                padding-left: 3.5rem;
            }

            .top-nav {
                min-height: 72px;
                height: auto;
                background: rgba(255, 255, 255, 0.92);
                backdrop-filter: blur(10px);
                border-bottom: 1px solid rgba(0, 73, 30, 0.08);
                padding: 0.5rem 2rem;
                position: sticky;
                top: 0;
                z-index: 1020;
            }

            .main-content {
                flex: 1;
                padding: 0;
                overflow-y: auto;
            }

            .card {
                border: 1px solid rgba(0, 73, 30, 0.08);
                border-radius: 12px;
                box-shadow: 0 10px 24px rgba(0, 73, 30, 0.06);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .card-stat:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            }

            .card-header {
                background: linear-gradient(90deg, rgba(2, 104, 30, 0.06), rgba(255, 198, 0, 0.06));
                border-bottom: 1px solid rgba(0, 73, 30, 0.08);
                padding: 1rem 1.25rem;
            }

            .btn-cmu {
                background-color: var(--cmu-yellow);
                color: var(--cmu-green);
                border: 1px solid var(--cmu-yellow);
                font-weight: 700;
                padding: 0.5rem 1.25rem;
                border-radius: 8px;
                font-size: 0.875rem;
            }

            .btn-cmu:hover {
                background-color: var(--cmu-yellow-hover);
                border-color: var(--cmu-yellow-hover);
                color: var(--cmu-green);
            }

            .stat-label {
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-weight: 700;
                color: #6b7280;
                margin-bottom: 0.25rem;
            }

            .stat-value {
                font-size: 1.875rem;
                font-weight: 300;
                line-height: normal;
            }

            .sidebar-footer {
                background: rgba(255, 255, 255, 0.04);
                padding: 1.5rem;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
            }

            #sidebar .brand-logo {
                width: 46px;
                height: 46px;
                object-fit: contain;
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 198, 0, 0.22);
                padding: 3px;
                filter: none;
                image-rendering: auto;
            }

            .sidebar-footer .user-pill {
                width: 38px;
                height: 38px;
                background: linear-gradient(135deg, rgba(255, 198, 0, 0.3), rgba(255, 255, 255, 0.12));
                border: 1px solid rgba(255, 255, 255, 0.1);
                font-size: 0.8125rem;
                font-weight: 700;
                color: white;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .sidebar-footer .btn-light {
                background: var(--cmu-yellow);
                border: 1px solid var(--cmu-yellow);
                color: var(--cmu-green) !important;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-footer .btn-light:hover {
                background: var(--cmu-yellow-hover);
                border-color: var(--cmu-yellow-hover);
                color: var(--cmu-green) !important;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(255, 198, 0, 0.24);
            }

            .sidebar-footer .btn-outline-light {
                background: rgba(255, 255, 255, 0.06);
                border-color: rgba(255, 255, 255, 0.22);
                color: white;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-footer .btn-outline-light:hover {
                background: rgba(255, 255, 255, 0.12);
                border-color: rgba(255, 255, 255, 0.36);
                transform: translateY(-2px);
            }

            .card small {
                font-size: 0.75rem;
            }

            @media (max-width: 992px) {
                #sidebar {
                    position: relative;
                    width: 100%;
                    min-width: 100%;
                    min-height: auto;
                }

                .layout-wrapper {
                    overflow: auto !important;
                    height: auto !important;
                    min-height: 100vh;
                }

                .top-nav {
                    padding: 0 1rem;
                }

                .main-content {
                    padding: 1.5rem;
                    overflow: visible !important;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="layout-wrapper d-flex flex-column flex-lg-row overflow-hidden min-vh-100">
            @include('layouts.navigation')

            <main class="d-flex flex-column flex-grow-1" style="min-width: 0;">
                <header class="top-nav d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1" style="min-width: 0;">
                        @if(isset($header))
                            {{ $header }}
                        @else
                            <h2 class="h5 fw-bold mb-0 text-dark">Dashboard</h2>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge rounded-pill" style="background: rgba(255, 198, 0, 0.18); color: #173323; border: 1px solid rgba(255, 198, 0, 0.28); font-weight: 600; padding: 0.55rem 0.85rem;">RGMO-IRMS</span>
                    </div>
                </header>

                <div class="main-content">
                    @if (session('success'))
                        <div class="container-fluid mt-4">
                            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                                <div class="d-flex align-items-center">
                                    <i data-lucide="check-circle" class="me-2" style="width: 18px"></i>
                                    <span>{{ session('success') }}</span>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}

                    <div class="container-fluid">
                        <footer class="mt-5 pt-4 text-center border-top mb-4">
                            <p class="text-muted" style="font-size: 10px; color: #6b7280 !important;">© {{ date('Y') }} Central Mindanao University - RGMO-IRMS • System v1.0</p>
                        </footer>
                    </div>
                </div>
            </main>
        </div>

        <script>
            lucide.createIcons();

            // Interactivity for Sidebar Dropdowns
            document.querySelectorAll('#sidebar details').forEach((details) => {
                details.addEventListener('click', (e) => {
                    // Close other open details if we're opening a new one
                    if (!details.open) {
                        document.querySelectorAll('#sidebar details[open]').forEach((other) => {
                            if (other !== details) {
                                other.removeAttribute('open');
                            }
                        });
                    }
                });
            });

            // Handle hover state for parent items when summary is hovered
            document.querySelectorAll('#sidebar summary').forEach(summary => {
                summary.addEventListener('mouseenter', () => {
                    summary.parentElement.classList.add('hover-active');
                });
                summary.addEventListener('mouseleave', () => {
                    summary.parentElement.classList.remove('hover-active');
                });
            });
        </script>
    </body>
</html>
