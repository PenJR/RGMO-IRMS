<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }} - {{ isset($header) ? strip_tags($header) : 'Dashboard' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
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
                --sidebar-collapsed-width: 86px;
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
                transition: width 0.22s ease, min-width 0.22s ease;
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

            /* Sidebar refresh */
            #sidebar {
                background:
                    linear-gradient(180deg, #005323 0%, #003f1c 46%, #003516 100%);
                border-right: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 12px 0 32px rgba(0, 49, 20, 0.18);
                overflow: hidden;
            }

            .sidebar-brand {
                padding: 1.4rem 1.25rem 1.15rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                background: rgba(0, 0, 0, 0.08);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
            }

            .sidebar-brand > .d-flex {
                min-width: 0;
                flex: 1 1 auto;
            }

            #sidebar .brand-logo {
                width: 42px;
                height: 42px;
                padding: 3px;
                background: rgba(255, 198, 0, 0.08);
                border: 1px solid rgba(255, 198, 0, 0.34);
                box-shadow: 0 0 0 4px rgba(255, 198, 0, 0.06);
            }

            .sidebar-title {
                margin: 0;
                font-size: 0.86rem;
                font-weight: 800;
                color: #ffffff;
                letter-spacing: 0;
                line-height: 1.1;
            }

            .sidebar-kicker {
                margin: 0.22rem 0 0;
                color: rgba(255, 255, 255, 0.56);
                font-size: 0.64rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar-toggle {
                width: 34px;
                height: 34px;
                flex: 0 0 34px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.88);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                line-height: 1;
                transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
            }

            .sidebar-toggle:hover {
                background: rgba(255, 198, 0, 0.16);
                border-color: rgba(255, 198, 0, 0.34);
                color: #ffffff;
            }

            .sidebar-toggle-icon {
                width: 18px;
                height: 18px;
            }

            .sidebar-toggle-expand {
                display: none;
            }

            .min-w-0 {
                min-width: 0;
            }

            .sidebar-menu {
                gap: 0.18rem;
                padding: 0.85rem 0.75rem 1rem;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: rgba(255, 255, 255, 0.28) transparent;
            }

            #sidebar .nav-link,
            #sidebar .nav-group-toggle {
                min-height: 44px;
                padding: 0.58rem 0.7rem;
                border: 1px solid transparent;
                border-left: 0;
                border-radius: 10px;
                color: rgba(255, 255, 255, 0.82);
                font-size: 0.86rem;
                font-weight: 600;
                line-height: 1.2;
                gap: 0.75rem;
                position: relative;
            }

            #sidebar .nav-link:hover,
            #sidebar .nav-group-toggle:hover,
            #sidebar .nav-group[open] > summary .nav-group-toggle {
                padding-left: 0.7rem;
                color: #ffffff;
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(255, 255, 255, 0.08);
                text-decoration: none;
            }

            #sidebar .nav-link.active {
                padding-left: 0.7rem;
                color: var(--cmu-green);
                background: var(--cmu-yellow);
                border-color: rgba(255, 198, 0, 0.92);
                font-weight: 800;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.14);
            }

            #sidebar .nav-link.active::before,
            #sidebar .nav-group.active > summary .nav-group-toggle::before {
                content: "";
                width: 4px;
                height: 22px;
                position: absolute;
                left: -0.75rem;
                top: 50%;
                transform: translateY(-50%);
                background: var(--cmu-yellow);
                border-radius: 0 6px 6px 0;
            }

            #sidebar .nav-group {
                margin: 0;
            }

            #sidebar .nav-group.active > summary .nav-group-toggle {
                color: #ffffff;
                background: rgba(255, 255, 255, 0.1);
                border-color: rgba(255, 255, 255, 0.1);
                font-weight: 800;
            }

            #sidebar .nav-icon {
                width: 30px;
                height: 30px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 30px;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.9);
            }

            #sidebar .nav-link [data-lucide],
            #sidebar .nav-group-toggle [data-lucide] {
                width: 17px;
                height: 17px;
                color: currentColor;
                stroke-width: 2.2;
            }

            #sidebar .nav-link.active .nav-icon {
                background: rgba(0, 73, 30, 0.12);
                color: var(--cmu-green);
            }

            #sidebar .nav-group.active > summary .nav-icon,
            #sidebar .nav-group[open] > summary .nav-icon {
                background: rgba(255, 198, 0, 0.16);
                color: var(--cmu-yellow);
            }

            #sidebar .nav-label {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #sidebar .chevron {
                margin-left: auto;
                width: 15px;
                height: 15px;
                opacity: 0.62;
                transition: transform 0.2s ease, opacity 0.2s ease;
            }

            #sidebar .nav-group[open] .chevron {
                transform: rotate(180deg);
                opacity: 1;
            }

            #sidebar .nav-submenu {
                margin: 0.25rem 0 0.35rem 2.05rem;
                padding: 0.25rem 0 0.25rem 0.65rem;
                background: transparent;
                border-left: 1px solid rgba(255, 255, 255, 0.16);
                animation: slideDown 0.22s ease-out;
            }

            #sidebar .nav-submenu .nav-link {
                min-height: 34px;
                padding: 0.42rem 0.65rem;
                border-radius: 8px;
                color: rgba(255, 255, 255, 0.68);
                font-size: 0.78rem;
                font-weight: 600;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }

            #sidebar .nav-submenu .nav-link:last-child {
                border-bottom-color: transparent;
            }

            #sidebar .nav-submenu .nav-link:hover,
            #sidebar .nav-submenu .nav-link.active {
                padding-left: 0.65rem;
                color: #ffffff;
                background: rgba(255, 255, 255, 0.08);
                opacity: 1;
            }

            #sidebar .nav-submenu .nav-link.active {
                color: var(--cmu-green);
                background: var(--cmu-yellow);
            }

            #sidebar .dropdown-divider {
                margin: 0.35rem 0.65rem;
                opacity: 0.16;
            }

            .nav-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.45rem;
                height: 1.25rem;
                padding: 0 0.45rem;
                border-radius: 999px;
                background: var(--cmu-yellow);
                color: var(--cmu-green);
                font-size: 0.64rem;
                font-weight: 800;
                line-height: 1;
                text-transform: uppercase;
            }

            .nav-badge-soft {
                background: rgba(255, 198, 0, 0.14);
                color: #fff2a8;
                border: 1px solid rgba(255, 198, 0, 0.28);
                font-size: 0.56rem;
                letter-spacing: 0.04em;
            }

            .sidebar-footer {
                margin: 0 0.75rem 0.9rem;
                padding: 0.85rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                background: rgba(255, 255, 255, 0.06);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
            }

            .sidebar-user {
                min-width: 0;
            }

            .sidebar-footer .user-pill {
                width: 38px;
                height: 38px;
                flex: 0 0 38px;
                border-radius: 10px !important;
                background: var(--cmu-yellow);
                color: var(--cmu-green);
                border: 1px solid rgba(255, 198, 0, 0.4);
                font-size: 0.78rem;
                font-weight: 800;
            }

            .sidebar-user-name,
            .sidebar-user-email {
                margin: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .sidebar-user-name {
                color: #ffffff;
                font-size: 0.76rem;
                font-weight: 800;
            }

            .sidebar-user-email {
                margin-top: 0.12rem;
                color: rgba(255, 255, 255, 0.54);
                font-size: 0.64rem;
            }

            .sidebar-footer .btn-light,
            .sidebar-footer .btn-outline-light {
                min-height: 32px;
                padding: 0.45rem 0.65rem;
                border-radius: 8px;
                font-size: 0.62rem;
                letter-spacing: 0.06em;
            }

            @media (min-width: 993px) {
                body.sidebar-collapsed #sidebar {
                    width: var(--sidebar-collapsed-width);
                    min-width: var(--sidebar-collapsed-width);
                }

                body.sidebar-collapsed .sidebar-brand {
                    padding: 1rem 0.65rem;
                    justify-content: center;
                    flex-direction: column;
                    gap: 0.7rem;
                }

                body.sidebar-collapsed .sidebar-brand > .d-flex {
                    flex: 0 0 auto;
                    justify-content: center;
                    gap: 0 !important;
                    width: 100%;
                }

                body.sidebar-collapsed .sidebar-brand .min-w-0 {
                    display: none;
                }

                body.sidebar-collapsed #sidebar .brand-logo {
                    width: 38px;
                    height: 38px;
                }

                body.sidebar-collapsed .sidebar-title,
                body.sidebar-collapsed .sidebar-kicker,
                body.sidebar-collapsed #sidebar .nav-label,
                body.sidebar-collapsed #sidebar .chevron,
                body.sidebar-collapsed #sidebar .nav-submenu,
                body.sidebar-collapsed .sidebar-user-name,
                body.sidebar-collapsed .sidebar-user-email,
                body.sidebar-collapsed .sidebar-footer .mt-3,
                body.sidebar-collapsed .nav-badge-soft {
                    display: none !important;
                }

                body.sidebar-collapsed .sidebar-toggle {
                    width: 36px;
                    height: 36px;
                }

                body.sidebar-collapsed .sidebar-toggle-collapse {
                    display: none;
                }

                body.sidebar-collapsed .sidebar-toggle-expand {
                    display: block;
                }

                body.sidebar-collapsed .sidebar-menu {
                    padding: 0.85rem 0.65rem 1rem;
                    align-items: center;
                    overflow-x: hidden;
                }

                body.sidebar-collapsed #sidebar .nav-group {
                    width: 48px;
                }

                body.sidebar-collapsed #sidebar .nav-link,
                body.sidebar-collapsed #sidebar .nav-group-toggle {
                    width: 48px;
                    min-height: 48px;
                    justify-content: center;
                    padding: 0;
                    gap: 0;
                    border-radius: 12px;
                    margin-inline: auto;
                }

                body.sidebar-collapsed #sidebar .nav-link:hover,
                body.sidebar-collapsed #sidebar .nav-link.active,
                body.sidebar-collapsed #sidebar .nav-group-toggle:hover,
                body.sidebar-collapsed #sidebar .nav-group[open] > summary .nav-group-toggle {
                    padding-left: 0;
                }

                body.sidebar-collapsed #sidebar .nav-link.active::before,
                body.sidebar-collapsed #sidebar .nav-group.active > summary .nav-group-toggle::before {
                    left: -0.65rem;
                    height: 24px;
                }

                body.sidebar-collapsed #sidebar .nav-icon {
                    margin: 0;
                    width: 32px;
                    height: 32px;
                    flex-basis: 32px;
                }

                body.sidebar-collapsed #sidebar .nav-badge:not(.nav-badge-soft) {
                    position: absolute;
                    top: 4px;
                    right: 2px;
                    min-width: 1rem;
                    height: 1rem;
                    padding: 0 0.28rem;
                    font-size: 0.54rem;
                }

                body.sidebar-collapsed .sidebar-footer {
                    margin: 0 0.65rem 0.9rem;
                    padding: 0.55rem;
                    display: flex;
                    justify-content: center;
                }

                body.sidebar-collapsed .sidebar-user {
                    justify-content: center;
                    gap: 0 !important;
                }

                body.sidebar-collapsed .sidebar-footer .user-pill {
                    width: 34px;
                    height: 34px;
                    flex-basis: 34px;
                    font-size: 0.68rem;
                }
            }

            @media (max-width: 992px) {
                #sidebar {
                    position: relative;
                    width: 100%;
                    min-width: 100%;
                    min-height: auto;
                }

                .sidebar-toggle {
                    display: none;
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

            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarStorageKey = 'rgmoSidebarCollapsed';
            const applySidebarState = (collapsed) => {
                document.body.classList.toggle('sidebar-collapsed', collapsed);

                if (sidebarToggle) {
                    sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
                    sidebarToggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
                }
            };

            applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');

            sidebarToggle?.addEventListener('click', () => {
                const collapsed = !document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(sidebarStorageKey, String(collapsed));
                applySidebarState(collapsed);
            });

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

            const notificationBadge = document.getElementById('notification-unread-badge');
            if (notificationBadge) {
                fetch('{{ route('notifications.unread-count') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : null)
                    .then((payload) => {
                        if (!payload) return;

                        const count = Number(payload.count ?? 0);
                        notificationBadge.textContent = count;
                        notificationBadge.classList.toggle('d-none', count <= 0);
                    })
                    .catch(() => {});
            }
        </script>

        @stack('scripts')
    </body>
</html>
