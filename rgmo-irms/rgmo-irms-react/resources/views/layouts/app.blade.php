<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RGMO-IRMS') - Central Mindanao University</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
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
        }
        
        #sidebar .nav-link:hover {
            color: white;
            background: var(--cmu-dark-green);
        }
        
        #sidebar .nav-link.active {
            color: var(--cmu-gold);
            background: rgba(255, 204, 0, 0.1);
            border-left-color: var(--cmu-gold);
            font-weight: 600;
        }

        .top-nav {
            height: 64px;
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
            tracking: 0.05em;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 300;
            line-height: normal;
        }
    </style>
</head>
<body>
    <div class="d-flex overflow-hidden" style="height: 100vh;">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="p-4 border-bottom border-dark-subtle mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; background: var(--cmu-gold); color: var(--cmu-green); font-size: 1.25rem;">U</div>
                    <div>
                        <p class="text-uppercase mb-0 opacity-50" style="font-size: 10px; letter-spacing: 0.1em;">Central Mindanao</p>
                    </div>
                </div>
            </div>
            
            <div class="nav flex-column flex-grow-1">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }} d-flex align-items-center">
                    <i data-lucide="layout-dashboard" class="me-3" style="width: 18px"></i> Dashboard
                </a>
                <a href="{{ route('items.index') }}" class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }} d-flex align-items-center">
                    <i data-lucide="package" class="me-3" style="width: 18px"></i> Inventory
                </a>
                <a href="#" class="nav-link d-flex align-items-center">
                    <i data-lucide="clipboard-list" class="me-3" style="width: 18px"></i> Resource Requests
                </a>
                <a href="#" class="nav-link d-flex align-items-center">
                    <i data-lucide="bar-chart-3" class="me-3" style="width: 18px"></i> Reports
                </a>
            </div>

            <div class="p-4 mt-auto border-top border-dark-subtle bg-dark-subtle" style="background: rgba(0,0,0,0.1) !important;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded bg-white bg-opacity-25 d-flex align-items-center justify-content-center text-xs" style="width: 32px; height: 32px; font-size: 0.75rem;">AD</div>
                    <div class="overflow-hidden">
                        <p class="mb-0 fw-bold text-truncate" style="font-size: 0.75rem;">Administrator</p>
                        <p class="mb-0 opacity-50 text-truncate" style="font-size: 10px;">admin@cmu.edu.ph</p>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="d-flex flex-column flex-grow-1">
            <!-- Topbar -->
            <header class="top-nav d-flex justify-content-between align-items-center">
                <h2 class="h5 fw-bold mb-0 text-dark">@yield('page_title')</h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge rounded-pill bg-light text-muted fw-normal px-3 py-2 border">Session: 2024-Q3</span>
                    <button class="btn btn-cmu btn-sm shadow-sm">+ New Resource</button>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i data-lucide="check-circle" class="me-2" style="width: 18px"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
                
                <footer class="mt-5 pt-4 text-center border-top">
                    <p class="text-muted" style="font-size: 10px; color: #9ca3af !important;">© 2024 Central Mindanao University - RGMO-IRMS (Laravel Blade Edition) • System v1.2.0-stable</p>
                </footer>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
