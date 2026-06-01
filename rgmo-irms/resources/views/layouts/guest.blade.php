<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --cmu-green: #006837;
                --cmu-dark-green: #004d29;
                --cmu-gold: #FFCC00;
            }

            body {
                background-color: #004d29;
                font-family: 'Inter', system-ui, sans-serif;
                color: #1f2937;
                background-image: 
                    radial-gradient(at 0% 0%, rgba(0, 104, 55, 0.8) 0, transparent 50%), 
                    radial-gradient(at 100% 100%, rgba(255, 204, 0, 0.15) 0, transparent 50%),
                    linear-gradient(135deg, #004d29 0%, #002b17 100%);
                background-attachment: fixed;
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
                background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
                pointer-events: none;
                z-index: 0;
            }

            .floating-blobs {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: -1;
                pointer-events: none;
            }

            .blob {
                position: absolute;
                width: 500px;
                height: 500px;
                background: var(--cmu-gold);
                filter: blur(100px);
                opacity: 0.05;
                border-radius: 50%;
                animation: float 20s infinite alternate;
            }

            @keyframes float {
                from { transform: translate(0, 0) scale(1); }
                to { transform: translate(100px, 100px) scale(1.2); }
            }

            .transition-all {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .feature-card:hover {
                background: rgba(255, 255, 255, 0.08) !important;
                transform: translateX(10px);
                border-color: rgba(255, 204, 0, 0.3) !important;
            }

            .login-card {
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 20px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                width: 100%;
                max-width: 480px;
                padding: 3rem;
            }

            .input-group-text {
                background: transparent;
                border-right: none;
            }

            .form-control {
                border-radius: 8px;
                padding: 0.75rem 1rem;
            }

            .form-control:focus {
                box-shadow: 0 0 0 4px rgba(0, 104, 55, 0.15);
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="floating-blobs">
            <div class="blob" style="top: -10%; left: -10%; background: var(--cmu-gold); animation-delay: 0s;"></div>
            <div class="blob" style="bottom: -10%; right: -10%; background: var(--cmu-green); animation-delay: -5s; opacity: 0.1;"></div>
        </div>

        <div class="container py-5">
            <div class="row align-items-center justify-content-center g-5 min-vh-100">
                <!-- Info Column (Visible on Desktop) -->
                <div class="col-lg-6 d-none d-lg-block text-white">
                    <div class="pe-lg-5">
                        <img src="{{ asset('images/logo.png') }}" alt="CMU Logo" style="width: 120px; height: 120px; margin-bottom: 2.5rem; filter: drop-shadow(0 0 20px rgba(255, 204, 0, 0.3)); transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <h1 class="display-3 fw-bold mb-2" style="color: var(--cmu-gold); letter-spacing: -0.04em; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">RGMO-IRMS</h1>
                        <p class="lead fw-medium mb-5 opacity-90" style="font-size: 1.25rem; border-left: 3px solid var(--cmu-gold); padding-left: 1.5rem;">Resource Generation Management Office<br><span class="opacity-75" style="font-size: 1.1rem;">Inventory & Resource Management System</span></p>
                        
                        <div class="space-y-4">
                            <div class="feature-card d-flex align-items-center gap-4 mb-4 p-3 rounded-4 transition-all" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                <div class="bg-white bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px; min-width: 60px;">
                                    <i data-lucide="package" class="text-warning" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-gold)">Asset Tracking</h5>
                                    <p class="small mb-0 opacity-75">Precision monitoring of agricultural stocks and nursery equipment.</p>
                                </div>
                            </div>
                            <!-- New: Environmental Impact -->
                            <div class="feature-card d-flex align-items-center gap-4 mb-4 p-3 rounded-4 transition-all" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                <div class="bg-white bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px; min-width: 60px;">
                                    <i data-lucide="leaf" class="text-warning" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-gold)">Sustainability Focus</h5>
                                    <p class="small mb-0 opacity-75">Promoting green initiatives through digital resource optimization.</p>
                                </div>
                            </div>
                            <div class="feature-card d-flex align-items-center gap-4 mb-4 p-3 rounded-4 transition-all" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                <div class="bg-white bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px; min-width: 60px;">
                                    <i data-lucide="file-check" class="text-warning" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-gold)">Resource Allocation</h5>
                                    <p class="small mb-0 opacity-75">Optimized workflow for supplies issuance and project distribution.</p>
                                </div>
                            </div>
                            <div class="feature-card d-flex align-items-center gap-4 mb-4 p-3 rounded-4 transition-all" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                                <div class="bg-white bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px; min-width: 60px;">
                                    <i data-lucide="sparkles" class="text-warning" style="width: 28px; height: 28px;"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--cmu-gold)">AI Insights</h5>
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
                            <img src="{{ asset('images/logo.png') }}" alt="Logo" style="width: 50px; height: 50px;">
                        </div>
                        
                        {{ $slot }}
                    </div>
                    
                    <div class="text-center mt-4 text-white opacity-50 d-lg-none">
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
