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

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --cmu-green: #006837;
                --cmu-dark-green: #004d29;
                --cmu-gold: #FFCC00;
                --sidebar-width: 260px;
            }

            body {
                background-color: #f9fafb;
                font-family: 'Inter', system-ui, sans-serif;
                color: #1f2937;
            }

            #sidebar {
                width: var(--sidebar-width);
                min-height: 100vh;
                background: var(--cmu-green);
                color: white;
                display: flex;
                flex-direction: column;
            }

            #sidebar .nav-link {
                color: #d1d5db;
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
                transition: all 0.2s;
                border-left: 4px solid transparent;
                display: flex;
                align-items: center;
            }

            #sidebar .nav-link:hover {
                color: white;
                background: var(--cmu-dark-green);
                text-decoration: none;
            }

            #sidebar .nav-link.active {
                color: var(--cmu-gold);
                background: rgba(255, 204, 0, 0.1);
                border-left-color: var(--cmu-gold);
                font-weight: 600;
            }

            .top-nav {
                height: 72px;
                background: white;
                border-bottom: 1px solid #e5e7eb;
                padding: 0 2rem;
            }

            .main-content {
                flex: 1;
                padding: 2rem;
                overflow-y: auto;
            }

            .card {
                border: 1px solid #f3f4f6;
                border-radius: 12px;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            }

            .card-header {
                background: white;
                border-bottom: 1px solid #f3f4f6;
                padding: 1rem 1.25rem;
            }

            .btn-cmu {
                background-color: var(--cmu-green);
                color: white;
                font-weight: 600;
                padding: 0.5rem 1.25rem;
                border-radius: 6px;
                font-size: 0.875rem;
            }

            .btn-cmu:hover {
                background-color: var(--cmu-dark-green);
                color: white;
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
                background: rgba(0,0,0,0.08);
                padding: 1rem 1.5rem;
            }

            .sidebar-footer .user-pill {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }

            .card small {
                font-size: 0.75rem;
            }

            @media (max-width: 992px) {
                #sidebar {
                    position: relative;
                    width: 100%;
                    min-height: auto;
                }

                .top-nav {
                    padding: 0 1rem;
                }

                .main-content {
                    padding: 1.5rem;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="d-flex overflow-hidden min-vh-100">
            @include('layouts.navigation')

            <main class="d-flex flex-column flex-grow-1">
                <header class="top-nav d-flex justify-content-between align-items-center">
                    <div>
                        @if(isset($header))
                            {{ $header }}
                        @else
                            <h2 class="h5 fw-bold mb-0 text-dark">Dashboard</h2>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge rounded-pill bg-light text-muted fw-normal px-3 py-2 border">RGMO-IRMS</span>
                    </div>
                </header>

                <div class="main-content">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                            <div class="d-flex align-items-center">
                                <i data-lucide="check-circle" class="me-2" style="width: 18px"></i>
                                <span>{{ session('success') }}</span>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    {{ $slot }}

                    <footer class="mt-5 pt-4 text-center border-top">
                        <p class="text-muted" style="font-size: 10px; color: #9ca3af !important;">© {{ date('Y') }} Central Mindanao University - RGMO-IRMS • System v1.0</p>
                    </footer>
                </div>
            </main>
        </div>

        <script>lucide.createIcons();</script>
    </body>
</html>
