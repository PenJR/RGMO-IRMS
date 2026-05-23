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
                background-color: #f3f4f6;
                font-family: 'Inter', system-ui, sans-serif;
                color: #1f2937;
                background-image: linear-gradient(135deg, var(--cmu-green) 0%, var(--cmu-dark-green) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .login-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 420px;
                padding: 2.5rem;
            }

            .brand-logo {
                background: white;
                width: 80px;
                height: 80px;
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: -4rem auto 1.5rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }

            .form-control:focus {
                border-color: var(--cmu-green);
                box-shadow: 0 0 0 0.25rem rgba(0, 104, 55, 0.1);
            }

            .btn-cmu {
                background-color: var(--cmu-green);
                color: white;
                font-weight: 600;
                padding: 0.75rem;
                border: none;
                transition: all 0.2s;
            }

            .btn-cmu:hover {
                background-color: var(--cmu-dark-green);
                color: white;
                transform: translateY(-1px);
            }

            .auth-label {
                font-weight: 600;
                font-size: 0.875rem;
                color: #374151;
                margin-bottom: 0.5rem;
            }

            .forgot-link {
                color: var(--cmu-green);
                text-decoration: none;
                font-size: 0.8125rem;
                font-weight: 500;
            }

            .forgot-link:hover {
                color: var(--cmu-dark-green);
                text-decoration: underline;
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="login-card">
            <div class="brand-logo">
                <a href="/">
                    <x-application-logo class="w-12 h-12 text-cmu-green" style="color: var(--cmu-green)" />
                </a>
            </div>
            
            <div class="text-center mb-4">
                <h4 class="fw-bold mb-1">RGMO-IRMS</h4>
            </div>

            {{ $slot }}
        </div>
    </body>
</html>
